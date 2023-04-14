<?php

namespace App\Mob407\Actions;

use App\Mob407\Models\Driver;
use App\Mob407\Tools\DriverReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;

class CheckDriversToResetBalance
{
    public static function query(): Builder
    {
        return Driver::query()
            ->affected()
            ->hasOnlyBusinessSessions()
            ->hasNoPaymentMethod()
            ->where('balance', '<', 0);
    }

    public static function run(ConsoleOutput $output, string $reportFolder, string $migrationsFolder): void
    {
        /** @var Driver[]|Collection $drivers */
        $drivers = self::query()->get();

        // Create report
        $reportFile  = $reportFolder . '/drivers_to_reset_balance_only.csv';
        $reportOutput = new StreamOutput(fopen($reportFile, 'w+'));

        DriverReport::print($drivers, $reportOutput);

        // create migration
        $migrationFile = $migrationsFolder . '/reset_balance_migration.sql';
        $migrationOutput = new StreamOutput(fopen($migrationFile, 'w+'));
        $migrationOutput->write(sprintf(self::getMigrationSql($drivers->pluck('id')->toArray())));

        $output->writeln(sprintf(' - Report file: %s', $reportFile));
        $output->writeln(sprintf(' - Migration file: %s', $migrationFile));
    }

    private static function getMigrationSql(array $driverIds): string
    {
        $driversIdsSql = '';
        $lineSize = 0;
        foreach ($driverIds as $driverId) {
            $lineSize++;
            if (!empty($driversIdsSql)) {
                $driversIdsSql .= ',';
            }

            if ($lineSize === 10) {
                $driversIdsSql .= PHP_EOL;
                $driversIdsSql .= '    ';
                $lineSize = 0;
            }

            $driversIdsSql .= $driverId;
        }

        $format =
<<<MIG
-- MOB-407 DMS sessions accreddited to private use
SELECT @instance_name := `instance_name` FROM `clb_config_instances` WHERE `is_default` = 1 LIMIT 1;
SELECT @dev_env := constant_value FROM clb_constants WHERE constant_name = 'DEVLOPMENT_ENVIRONMENT';
SELECT @eu_nos := IF('eu' = @instance_name, true, false);

-- Fix account balance for drivers who use only business session and have been charged for some of the sessions in September and January 2022
set @query_update_eu_prod = '
update clb_account
join clb_subscriber on clb_account.id = clb_subscriber.account_id
set clb_account.amount_left = 0
where clb_subscriber.id in (
    %s
);';

set @query_select_eu_prod = '
select
    clb_subscriber.id as user_id,
    clb_account.id as account_id,
    clb_account.amount_left as balance_before_update
from clb_subscriber
join clb_account on clb_subscriber.account_id = clb_account.id
where clb_subscriber.id in (
    %s
);';

set @query_update = IF (@eu_nos = 1 and @dev_env != 1, @query_update_eu_prod, 'SELECT 1;');
set @query_select = IF (@eu_nos = 1 and @dev_env != 1, @query_select_eu_prod, 'SELECT 1;');

-- Print current state to jenkins job logs
prepare stmt_select from @query_select;
execute stmt_select;
deallocate prepare stmt_select;

prepare stmt from @query_update;
execute stmt;
deallocate prepare stmt;

MIG;
        return sprintf($format, $driversIdsSql, $driversIdsSql);
    }
}