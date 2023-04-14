<?php

namespace App\Mob407\Actions;

use App\Mob407\Models\Driver;
use App\Mob407\Tools\DriverReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;

class CheckAlreadyResetDrivers
{
    public static function query(): Builder
    {
        return Driver::query()
            ->affected()
            ->hasOnlyBusinessSessions()
            ->hasNoPaymentMethod()
            ->where('balance', 0);
    }

    public static function run(ConsoleOutput $output, string $reportFolder): void
    {
        /** @var Driver[]|Collection $drivers */
        $drivers = self::query()->get();

        $reportFile = $reportFolder . '/already_reset_drivers.csv';
        $reportOutput = new StreamOutput(fopen($reportFile, 'w+'));

        DriverReport::print($drivers, $reportOutput);

        $output->writeln(sprintf(' - %d users have already fixed', $drivers->count()));
        $output->writeln(sprintf(' - Report saved to %s', $reportFile));
    }
}