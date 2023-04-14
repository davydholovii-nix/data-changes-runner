<?php

namespace App\Mob407\Actions;

use App\Mob407\Models\Driver;
use App\Mob407\Models\PaymentLog;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class PreparePaymentHistoryTable
{
    public static function run(ConsoleOutput $output, bool $force = false): void
    {
        if (!$force && DB::schema()->hasTable('payments_history')) {
            $output->writeln('Payments history table already exists');
            return;
        }

        $output->writeln(' - Creating payments history table');

        DB::schema()->dropIfExists('payments_history');
        DB::schema()->create('payments_history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->index();
            $table->integer('session_id')->index()->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_diff', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->timestamp('created_at');
        });

        $progress = new ProgressBar($output, Driver::query()->count());

        Driver::query()
            ->chunk(100, function ($drivers) use (&$progress) {
                /** @var Driver[] $drivers */
                foreach ($drivers as $driver) {
                    $progress->advance();

                    if (!$driver->id) {
                        continue;
                    }

                    $prevBalance = null;

                    /** @var PaymentLog[]|\Illuminate\Database\Eloquent\Collection $paymentLogs */
                    $paymentLogs = PaymentLog::query()
                        ->where('user_id', $driver->id)
                        ->whereNotNull('amount')
                        ->orderBy('create_date')
                        ->get();

                    foreach ($paymentLogs as $paymentLog) {
                        if ($prevBalance === null) {
                            $prevBalance = $paymentLog->account_balance;
                            continue;
                        }

                        if ($paymentLog->account_balance === $prevBalance) {
                            continue;
                        }

                        DB::table('payments_history')
                            ->insert([
                                'user_id' => $driver->id,
                                'session_id' => $paymentLog->vc_id,
                                'amount' => $paymentLog->amount,
                                'balance_before' => $prevBalance,
                                'balance_after' => $paymentLog->account_balance,
                                'balance_diff' => $paymentLog->account_balance - $prevBalance,
                                'created_at' => $paymentLog->create_date,
                            ]);

                        $prevBalance = $paymentLog->account_balance;
                    }

                    unset($paymentLogs);
                }
            });

        $progress->finish();
        $output->writeln("\r" . str_repeat(' ', 100));
        $output->writeln(' - Payments history table created');
    }
}