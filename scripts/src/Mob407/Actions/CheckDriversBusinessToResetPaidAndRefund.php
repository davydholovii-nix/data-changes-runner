<?php

namespace App\Mob407\Actions;

use App\Mob407\Models\Driver;
use App\Mob407\Tools\DriverReportWithRefund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;

class CheckDriversBusinessToResetPaidAndRefund
{
    public static function query(): Builder
    {
        return Driver::query()
            ->affected()
            ->hasOnlyBusinessSessions()
            ->hasPaymentMethod()
            ->where('balance', '<', 0);
    }

    public static function run(ConsoleOutput $output, string $reportFolder): void
    {
        /** @var Driver[]|Collection $drivers */
        $drivers = self::query()->get();

        $reportFile = $reportFolder . '/drivers_to_reset_paid_and_refund.csv';
        $reportOutput = new StreamOutput(fopen($reportFile, 'w+'));

        DriverReportWithRefund::print($drivers, $reportOutput);

        $output->writeln(sprintf(" - %d drivers have to be refunded and the paid status has to be restored", $drivers->count()));

        fclose($reportOutput->getStream());
    }
}