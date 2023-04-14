<?php

namespace App\Mob407\Tools;

use App\Mob407\Models\Driver;
use App\Mob407\Models\Enums\TransactionType;
use App\Mob407\Models\PaymentHistory;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\Console\Output\Output;

class DriverReportWithRefund
{
    public static function print(array|Collection $drivers, Output $output): void
    {
        $totalAmountToRefund = 0;

        $drivers->each(function (Driver $driver) use ($output, &$totalAmountToRefund) {
            $output->writeln(sprintf('"Driver ID:",%d,,,', $driver->id));

            if ($driver->has_jan_only) {
                $output->writeln(
                    '"Note:","Driver personal sessions could be business ' .
                    'sessions but they are marked as personal because MOB-627",,,'
                );
            }

            $output->writeln("session_id,amount,balance_before,balance_after,session_date");

            $driver->payments->each(function (PaymentHistory $history) use ($output, &$totalAmountToRefund) {
                $output->writeln(sprintf(
                    '%d,%s,%s,%s,"%s"',
                    $history->session_id ?: $history->payment_type,
                    $history->amount,
                    $history->balance_before,
                    $history->balance_after,
                    $history->created_at
                ));
            });

            $toRefund = $driver->payments()
                ->selectRaw('sum(amount) as to_refund')
                ->whereNotNull('session_id')
                ->where('balance_diff', '<', 0)
                ->whereExists(function ($query) {
                    $query->selectRaw(1)
                        ->from('external_vehicle_charge_ext')
                        ->whereRaw('clb_external_vehicle_charge_ext.evc_id = clb_payments_history.session_id')
                        ->where('external_vehicle_charge_ext.transaction_type', TransactionType::BUSINESS);
                })
                ->first()
                ->to_refund;

            $totalAmountToRefund += $toRefund;

            $output->writeln(sprintf('"Amount to refund:", %f,,,', $toRefund));

            $output->writeln(',,,,');
            $output->writeln(',,,,');
        });

        $output->writeln(sprintf('"Total amount to refund:", %f,,,', $totalAmountToRefund));
    }
}