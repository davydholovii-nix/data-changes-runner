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

class PrepareDriversTable
{
    public static function run(ConsoleOutput $output, bool $force = false): void
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
                    $table->tinyInteger('is_affected')->default(0);
                    $table->tinyInteger('has_jan_only')->default(0);
                    $table->decimal('balance', 10, 2)->default(0);
                });

            if (!DB::schema()->hasTable('users')) {
                $output->writeln(" - Failed to create users table");

                exit(1);
            }

            $uniqueUsersQuery = DB::table('external_vehicle_charge')
                ->selectRaw('DISTINCT(user_id) as id')
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

        if ($force) {
            $progress = new ProgressBar($output, $totalDrivers);

            // Detect if driver has personal sessions
            // Detect if driver has business sessions
            Driver::query()
                ->chunk(100, function ($drivers) use ($progress, $output) {
                    DB::connection()->beginTransaction();

                    try {
                        /** @var Driver[] $drivers */
                        foreach ($drivers as $driver) {
                            $progress->advance();
                            $hasPersonal = Session::query()
                                ->join(
                                    'external_vehicle_charge_ext',
                                    'external_vehicle_charge_ext.evc_id',
                                    '=',
                                    'external_vehicle_charge.id'
                                )
                                ->where(
                                    'external_vehicle_charge_ext.transaction_type',
                                    TransactionType::PERSONAL
                                )
                                ->where('user_id', $driver->id)
                                ->exists();
                            $hasBusiness = Session::query()
                                ->join(
                                    'external_vehicle_charge_ext',
                                    'external_vehicle_charge_ext.evc_id',
                                    '=',
                                    'external_vehicle_charge.id'
                                )
                                ->where(
                                    'external_vehicle_charge_ext.transaction_type',
                                    TransactionType::BUSINESS
                                )
                                ->where('user_id', $driver->id)
                                ->exists();

                            $driver->has_personal_sessions = $hasPersonal;
                            $driver->has_business_sessions = $hasBusiness;
                            $driver->save();
                        }

                        DB::connection()->commit();
                    } catch (Exception $e) {
                        DB::connection()->rollBack();

                        $output->writeln(" - Failed to update users table");
                        $output->writeln(" - " . $e->getMessage());

                        exit(1);
                    }
                });

            $progress->finish();
            $output->writeln("\r" . str_repeat(' ', 100));
        }

        $output->writeln(sprintf(
            ' - %d users with business sessions',
            Driver::query()->where('has_business_sessions', true)->count()
        ));
        $output->writeln(sprintf(
            ' - %d users with personal sessions',
            Driver::query()->where('has_personal_sessions', true)->count()
        ));

        if ($force) {
            $progress = new ProgressBar(
                $output,
                Driver::query()
                    ->where('has_business_sessions', true)
                    ->count()
            );

            // Detect if driver is affected by MOB-407
            Driver::query()
                ->where('has_business_sessions', true)
                ->chunk(100, function ($drivers) use ($progress, $output) {
                    DB::connection()->beginTransaction();

                    try {
                        /** @var Driver[] $drivers */
                        foreach ($drivers as $driver) {
                            $progress->advance();

                            /** @var PaymentLog|null $firstPayment */
                            $firstPayment = PaymentLog::query()
                                ->where('user_id', $driver->id)
                                ->orderBy('create_date')
                                ->first();

                            if ($firstPayment === null) { // Driver has no payments
                                continue;
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

                                if ($payment->session && $payment->session->isBusiness() && $payment->account_balance < $balance) {
                                    $driver->is_affected = true;
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
}