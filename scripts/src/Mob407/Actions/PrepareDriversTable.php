<?php

namespace App\Mob407\Actions;

use App\Mob407\Models\Driver;
use App\Mob407\Models\Enums\TransactionType;
use App\Mob407\Models\PaymentLog;
use App\Mob407\Models\Session;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;

class PrepareDriversTable
{
    public static function run(ConsoleOutput $output, string $reportFolder, string $sourcesDir, bool $force = false): void
    {
        if ($force) {
            $output->writeln(" - Dropping users table");
            DB::schema()->dropIfExists('users');

            $output->writeln(" - Creating users table");
            DB::schema()
                ->create('users', function (Blueprint $table) {
                    $table->integer('id')->primary();
                    $table->tinyInteger('has_personal_sessions')->default(0);
                    $table->tinyInteger('has_business_sessions')->default(0);
                    $table->tinyInteger('has_payments')->default(0);
                    $table->tinyInteger('is_affected')->default(0);
                    $table->tinyInteger('leave_for_manual_check')->default(0);
                    $table->tinyInteger('starts_negative')->default(0);
                    $table->tinyInteger('balance_goes_down_only')->default(0);
                    $table->tinyInteger('has_jan_only')->default(0);
                    $table->decimal('balance', 10, 2)->default(0);
                });

            if (!DB::schema()->hasTable('users')) {
                $output->writeln(" - Failed to create users table");

                exit(1);
            }

            $uniqueUsersQuery = DB::table('external_vehicle_charge')
                ->selectRaw('DISTINCT(user_id) as id')
                ->join('external_vehicle_charge_ext', 'external_vehicle_charge_ext.evc_id', '=', 'external_vehicle_charge.id')
                ->whereNotNull('user_id');
            Driver::query()
                ->insertUsing(['id'], $uniqueUsersQuery);

            Driver::query()
                ->where('id', 0)
                ->delete();
        }

        $totalDrivers = Driver::query()->count();

        $output->writeln(sprintf(
            " - %d users created",
            $totalDrivers,
        ));

        // After manual check I found 2 drivers affected by MOB-407 but for some reason their data is missing in the dump
        // Inserting data manually
        if ($force) {
            // Delete data for drivers that cannot be resolved correctly
            $usersToClear = [24746895, 27110655];
            DB::table('user_payment_log')
                ->join('external_vehicle_charge', 'external_vehicle_charge.id', '=', 'user_payment_log.vc_id')
                ->whereIn('external_vehicle_charge.user_id', $usersToClear)
                ->delete();
            DB::table('user_payment_log')
                ->join('external_vehicle_charge', 'external_vehicle_charge.id', '=', 'user_payment_log.vc_id')
                ->whereIn('external_vehicle_charge.user_id', $usersToClear)
                ->delete();
            DB::table('external_vehicle_charge')->whereIn('user_id', [24746895, 27110655])->delete();

            $evcFallback = ['network_id' => 1];
            $evceFallback = ['device_id' => 1, 'method_type' => 'null'];
            $plFallback = ['type' => 19, 'transaction_status' => 1];
            $sessionsToClear = [801869225,801835885,801835965,801850945,801886255,797915735,801805525];

            // Sessions data
            DB::table('external_vehicle_charge')->whereIn('id', $sessionsToClear)->delete();
            DB::table('external_vehicle_charge')
                ->insert([
                    // Driver 34754985
                    array_merge($evcFallback, ['id' => 801869225, 'total_amount_to_user' => 2.64, 'user_id' => 34754985, 'billing_time' => '2023-05-13 09:12:00']),
                    // Driver 36898695
                    array_merge($evcFallback, ['id' => 801835885, 'total_amount_to_user' => 0.07, 'user_id' => 36898695, 'billing_time' => '2023-05-11 14:37:10']),
                    array_merge($evcFallback, ['id' => 801835965, 'total_amount_to_user' => 19.36, 'user_id' => 36898695, 'billing_time' => '2023-05-11 15:00:55']),
                    array_merge($evcFallback, ['id' => 801850945, 'total_amount_to_user' => 20.65, 'user_id' => 36898695, 'billing_time' => '2023-05-12 10:16:02']),
                    array_merge($evcFallback, ['id' => 801886255, 'total_amount_to_user' => 28.54, 'user_id' => 36898695, 'billing_time' => '2023-05-14 10:07:15']),
                    // Driver 27110655
                    array_merge($evcFallback, ['id' => 797915735, 'total_amount_to_user' => 4.28, 'user_id' => 27110655, 'billing_time' => '2022-09-27 07:45:35']),
                    // Driver 24746895
                    array_merge($evcFallback, ['id' => 801805525, 'total_amount_to_user' => 13.13, 'user_id' => 24746895, 'billing_time' => '2023-05-10 10:57:10']),
                ]);
            DB::table('external_vehicle_charge_ext')->whereIn('evc_id', $sessionsToClear)->delete();
            DB::table('external_vehicle_charge_ext')
                ->insert([
                    // Driver 34754985
                    array_merge($evceFallback, ['evc_id' => 801869225, 'transaction_type' => 'BUSINESS']),
                    // Driver 36898695
                    array_merge($evceFallback, ['evc_id' => 801835885, 'transaction_type' => 'BUSINESS']),
                    array_merge($evceFallback, ['evc_id' => 801835965, 'transaction_type' => 'BUSINESS']),
                    array_merge($evceFallback, ['evc_id' => 801850945, 'transaction_type' => 'BUSINESS']),
                    array_merge($evceFallback, ['evc_id' => 801886255, 'transaction_type' => 'BUSINESS']),
                    // Driver 24746895
                    array_merge($evceFallback, ['evc_id' => 801805525, 'transaction_type' => 'BUSINESS']),
                    // Driver 27110655
                    array_merge($evceFallback, ['evc_id' => 797915735, 'transaction_type' => 'BUSINESS']),
                ]);

            // Payment logs data
            DB::table('user_payment_log')->whereIn('vc_id', $sessionsToClear)->delete();
            DB::table('user_payment_log')
                ->insert([
                    // Driver 34754985
                    array_merge($plFallback, ['user_id' => 34754985, 'vc_id' => null, 'amount' => 0, 'account_balance' => 0, 'create_date' => '2023-05-13 09:11:00']),
                    array_merge($plFallback, ['user_id' => 34754985, 'vc_id' => 801869225, 'amount' => 2.64, 'account_balance' => -2.64, 'create_date' => '2023-05-13 09:12:00']),
                    // Driver 36898695
                    array_merge($plFallback, ['user_id' => 36898695, 'vc_id' => null, 'amount' => 0, 'account_balance' => 0, 'create_date' => '2023-05-11 14:36:10']),
                    array_merge($plFallback, ['user_id' => 36898695, 'vc_id' => 801835885, 'amount' => 0.07, 'account_balance' => -0.07, 'create_date' => '2023-05-11 14:37:10']),
                    array_merge($plFallback, ['user_id' => 36898695, 'vc_id' => 801835965, 'amount' => 19.36, 'account_balance' => -19.43, 'create_date' => '2023-05-11 15:00:55']),
                    array_merge($plFallback, ['user_id' => 36898695, 'vc_id' => 801850945, 'amount' => 20.65, 'account_balance' => -40.08, 'create_date' => '2023-05-12 10:16:02']),
                    array_merge($plFallback, ['user_id' => 36898695, 'vc_id' => 801886255, 'amount' => 28.54, 'account_balance' => -68.62, 'create_date' => '2023-05-14 10:07:15']),
                    // Driver 24746895
                    array_merge($plFallback, ['user_id' => 24746895, 'vc_id' => null, 'amount' => 0, 'account_balance' => 0, 'create_date' => '2023-05-10 10:57:09']),
                    array_merge($plFallback, ['user_id' => 24746895, 'vc_id' => 801805525, 'amount' => 13.13, 'account_balance' => -13.13, 'create_date' => '2023-05-10 10:57:10']),
                    // Driver 27110655
                    array_merge($plFallback, ['user_id' => 27110655, 'vc_id' => null, 'amount' => 0, 'account_balance' => 0, 'create_date' => '2022-09-27 07:45:34']),
                    array_merge($plFallback, ['user_id' => 27110655, 'vc_id' => 797915735, 'amount' => 4.28, 'account_balance' => -4.28, 'create_date' => '2022-09-27 07:45:35']),
                ]);
        }

        if ($force) {
            $output->writeln(" - Calculating driver balance and checking if driver is affected by MOB-407");
            $progress = new ProgressBar(
                $output,
                Driver::query()->count()
            );
            $progress->start();

            $noPaymentDriversFilePath = $reportFolder . '/no_payment_drivers.csv';
            $noPaymentDriversFile = fopen($noPaymentDriversFilePath, 'w');
            $noPaymentsOutput = new StreamOutput($noPaymentDriversFile);

            // Detect if driver is affected by MOB-407
            Driver::query()
                ->chunk(100, function ($drivers) use ($progress, $output, $noPaymentsOutput) {
                    DB::connection()->beginTransaction();

                    try {
                        /** @var Driver[] $drivers */
                        foreach ($drivers as $driver) {
                            $progress->advance();

                            if (!self::hasBusinessSessions($driver)) {
                                continue;
                            }

                            $driver->has_business_sessions = true;
                            $driver->has_personal_sessions = self::hasPersonalRoamingSessions($driver);

                            /** @var PaymentLog|null $firstPayment */
                            $firstPayment = PaymentLog::query()
                                ->where('user_id', $driver->id)
                                ->orderBy('create_date')
                                ->first();

                            if ($firstPayment === null) { // Driver has no payments
                                $noPaymentsOutput->write($driver->id . ",");
                                continue;
                            }

                            if (
                                $firstPayment->amount > 0
                                && $firstPayment->account_balance < 0
                                && $firstPayment->type === PaymentLog::TYPE_ROAMING_SESSION
                            ) {
                                $driver->starts_negative = true;
                            }

                            /** @var PaymentLog[]|\Illuminate\Database\Eloquent\Collection $userPayments */
                            $userPayments = PaymentLog::query()
                                ->with('session')
                                ->where('user_id', $driver->id)
                                ->orderBy('create_date')
                                ->get();
                            $balance = $firstPayment->account_balance;

                            foreach ($userPayments as $payment) {
                                if ($payment->account_balance === $balance) {
                                    continue;
                                }

                                if ($driver->is_affected) {
                                    $balance = $payment->account_balance;
                                    continue;
                                }

                                $currentBalanceAsInt = (int)($balance * 100);
                                $paymentBalanceAsInt = (int)($payment->account_balance * 100);

                                if ($payment->session?->isBusiness() && $paymentBalanceAsInt < $currentBalanceAsInt) {
                                    $driver->is_affected = true;
                                }

                                if ($payment->isIncome()) {
                                    $driver->has_payments = true;
                                }

                                $balance = $payment->account_balance;
                            }

                            $driver->balance = $balance;
                            $driver->save();
                        }

                        DB::connection()->commit();
                    } catch (Exception $e) {
                        DB::connection()->rollBack();

                        $progress->finish();
                        $output->writeln("\r" . str_repeat(' ', 100));

                        $output->writeln(" - Failed to update users table");
                        $output->writeln(" - " . $e->getMessage());

                        exit(1);
                    }
                });

            $manualCheckDriversFile = $sourcesDir . "/affected_manual_check.txt";
            $manualCheckContent = file_get_contents($manualCheckDriversFile);
            $manualCheckDrivers = explode(',', $manualCheckContent);

            foreach ($manualCheckDrivers as $driverId) {
                $driver = Driver::query()->find($driverId);
                if ($driver) {
                    $driver->is_affected = true;
                    $driver->save();

                    /** @var PaymentLog|null $firstPayment */
                    $firstPayment = PaymentLog::query()
                        ->where('user_id', $driver->id)
                        ->orderBy('create_date')
                        ->first();

                    if ($firstPayment === null) { // Driver has no payments
                        $noPaymentsOutput->write($driver->id . ",");
                    }
                }
            }

            fclose($noPaymentDriversFile);

            if (file_exists($noPaymentDriversFilePath) && empty(file_get_contents($noPaymentDriversFilePath))) {
                unlink($noPaymentDriversFilePath);
            } else {
                $output->writeln(sprintf(' - Check %s for drivers with no payments', $noPaymentDriversFilePath));
            }

            $progress->finish();
            $output->writeln("\r" . str_repeat(' ', 100));
            $output->writeln(sprintf(
                ' - %d users affected by MOB-407',
                Driver::query()->where('is_affected', true)->count()
            ));
        }

        if ($force) {
            Driver::query()->affected()->update(['has_jan_only' => false]);

            $progress = new ProgressBar($output, Driver::query()->affected()->count());

            Driver::query()
                ->affected()
                ->hasPersonalSessions()
                ->chunk(100, function ($drivers) use ($progress, $output) {
                    DB::connection()->beginTransaction();

                    try {
                        /** @var Driver[] $drivers */
                        foreach ($drivers as $driver) {
                            $progress->advance();

                            $hasPersonalSessionsInJan = Session::query()
                                ->where('user_id', $driver->id)
                                ->where('billing_time', '>=', '2023-01-01')
                                ->where('billing_time', '<=', '2023-01-31')
                                ->whereHas('details', function (Builder $query) {
                                    $query->where('transaction_type', TransactionType::PERSONAL);
                                })
                                ->exists();

                            if (!$hasPersonalSessionsInJan) { // Driver has no payments
                                continue;
                            }

                            /** @var PaymentLog[]|\Illuminate\Database\Eloquent\Collection $userPayments */
                            $userPayments = PaymentLog::query()
                                ->with('session')
                                ->where('user_id', $driver->id)
                                ->orderBy('create_date')
                                ->get();

                            $hasJanOnly = true;

                            foreach ($userPayments as $payment) {
                                if ($payment->session && !$payment->session->isBusiness()) {
                                    $sessionDate = Carbon::parse($payment->session->billing_time);

                                    if (!$sessionDate->between('2023-01-01', '2023-01-31')) {
                                        $hasJanOnly = false;
                                        break;
                                    }
                                }
                            }

                            $driver->has_jan_only = $hasJanOnly;
                            $driver->save();
                        }

                        DB::connection()->commit();
                    } catch (Exception $e) {
                        DB::connection()->rollBack();

                        $progress->finish();
                        $output->writeln("\r" . str_repeat(' ', 100));

                        $output->writeln(" - Failed to update users table");
                        $output->writeln(" - " . $e->getMessage());

                        exit(1);
                    }
                });

            $progress->finish();
            $output->writeln("\r" . str_repeat(' ', 100));
        }

        $output->writeln(sprintf(
            ' - %d users with only Jan 2022 personal sessions',
            Driver::query()->where('has_jan_only', true)->count()
        ));
    }

    private static function hasBusinessSessions(Driver $driver): bool
    {
        return Session::query()
            ->join(
                'external_vehicle_charge_ext',
                'external_vehicle_charge_ext.evc_id',
                '=',
                'external_vehicle_charge.id'
            )
            ->join(
                'user_payment_log',
                'user_payment_log.vc_id',
                '=',
                'external_vehicle_charge.id'
            )
            ->where(
                'external_vehicle_charge_ext.transaction_type',
                TransactionType::BUSINESS
            )
            ->where('external_vehicle_charge.user_id', $driver->id)
            ->exists();
    }

    public static function hasPersonalRoamingSessions(Driver $driver): bool
    {
        return Session::query()
            ->select('external_vehicle_charge.id')
            ->join(
                'external_vehicle_charge_ext',
                'external_vehicle_charge_ext.evc_id',
                '=',
                'external_vehicle_charge.id'
            )
            ->join(
                'user_payment_log',
                'user_payment_log.vc_id',
                '=',
                'external_vehicle_charge.id'
            )
            ->where(
                'external_vehicle_charge_ext.transaction_type',
                TransactionType::PERSONAL
            )
            ->where('external_vehicle_charge.user_id', $driver->id)
            ->exists();
    }
}
