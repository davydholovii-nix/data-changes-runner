<?php

namespace App\Mob407\Actions\V2;

use App\Mob407\Actions\CheckDriversBusinessOnlyToRefund;
use App\Mob407\Actions\CheckDriversPersonalOnlyToRefund;
use App\Mob407\Models\Driver;
use App\Mob407\Models\Enums\TransactionType;
use App\Mob407\Models\Organization;
use App\Mob407\Models\SessionDetails;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\StreamOutput;

class CreateGroup2Report extends AbstractDriverReport
{

    public static function run(Output $output, string $reportFolder): void
    {
        $reportFile = $reportFolder . '/group_2.csv';
        $reportStream = fopen($reportFile, 'w');
        $reportOutput = new StreamOutput($reportStream);
        $reportOutput->writeln('driver_id,amount_to_refund,email,notify_email,first_name,middle_name,last_name,country,country_code,language,org_id,affected_sessions');

        $progress = new ProgressBar($output, CheckDriversBusinessOnlyToRefund::query()->count() + CheckDriversPersonalOnlyToRefund::query()->count());
        $progress->start();
        $total = self::processDrivers($progress, $reportOutput, CheckDriversBusinessOnlyToRefund::query(), 0.0);
        $total = self::processDrivers($progress, $reportOutput, CheckDriversPersonalOnlyToRefund::query(), $total);
        $progress->finish();
        $output->write("\r" . str_repeat(' ', 100) . "\r");
        $output->writeln(' - Done');

        $reportOutput->writeln(sprintf('Total: %s', $total));
    }

    private static function processDrivers(ProgressBar $progressBar, Output $reportOutput, Builder $query, float $total): float
    {
        $query->chunk(100, function ($drivers) use ($progressBar, $reportOutput, &$total) {
            foreach ($drivers as $driver) {
                $refundAmount = self::getAffectedSessionsQuery($driver)
                    ->sum('external_vehicle_charge.total_amount_to_user');
                $affectedSessions = self::getAffectedSessionsQuery($driver)
                    ->get('external_vehicle_charge.id')
                    ->pluck('id');

                $total += $refundAmount;
                // driver_id,amount_to_refund,email,notify_email,first_name,middle_name,last_name,country,country_code,language,org_id
                $reportOutput->writeln(sprintf('%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s',
                    $driver->id,
                    $refundAmount,
                    '"' . $driver->details->email . '"',
                    '"' . $driver->details->notify_email . '"',
                    '"' . $driver->details->first_name . '"',
                    '"' . $driver->details->middle_name . '"',
                    '"' . $driver->details->last_name . '"',
                    '"' . $driver->details->country_name . '"',
                    '"' . $driver->details->country_code . '"',
                    '"' . $driver->details->lang . '"',
                    $driver?->details?->org?->code,
                    '"' . $affectedSessions->join(',') . '"'
                ));
                $progressBar->advance();
            }
        });

        return $total;
    }
}
