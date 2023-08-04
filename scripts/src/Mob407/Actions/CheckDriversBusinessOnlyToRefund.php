<?php

namespace App\Mob407\Actions;

use App\Mob407\Models\Driver;
use App\Mob407\Tools\DriverReportWithRefund;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;

class CheckDriversBusinessOnlyToRefund
{
    public static function query(): Builder
    {
        return Driver::query()
            ->affected()
            ->hasOnlyBusinessSessions()
            ->hasPaymentMethod()
            ->where('balance', '>=', 0);
    }

    public static function run(ConsoleOutput $output, string $reportFolder)
    {
        $drivers = self::query()->get();

        $reportFile = $reportFolder . '/group_2.csv';
        $reportOutput = new StreamOutput(fopen($reportFile, 'a'));

        DriverReportWithRefund::print($drivers, $reportOutput);

        $output->writeln(sprintf(' - %d drivers to refund only', $drivers->count()));
        $output->writeln(sprintf(' - Report file: %s', $reportFile));
    }
}
