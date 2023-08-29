<?php

namespace App\Mob407;

use App\Mob407\V3\Reports\Group1Report;
use App\Mob407\V3\Reports\Group2Report;
use App\Mob407\V3\Reports\Group3Report;
use App\Mob407\V3\Reports\GroupWithRefundsReport;
use App\Mob407\V3\Tasks\CreateBalanceHistoryTableTask;
use App\Mob407\V3\Tasks\CreateCoulombTablesTask;
use App\Mob407\V3\Tasks\CreateDriversTableTask;
use App\Mob407\V3\Tasks\CreateDriversWithBusinessSessionsTableTask;
use App\Mob407\V3\Tasks\GenerateFakeZeroPaymentLogsTask;
use App\Mob407\V3\Tasks\ImportCoulombSessionsTask;
use App\Mob407\V3\Tasks\ImportUserPaymentLogTask;
use App\Mob407\V3\Tasks\InsertBalanceHistoryTask;
use App\Mob407\V3\Tasks\InsertDriversFromDetailsFileTask;
use App\Mob407\V3\Tasks\InsertDriversWithBusinessSessionsTask;
use App\Mob407\V3\Verifier;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\StreamOutput;

class Mob407AnalyzerV3
{
    public const OPTION_RECALCULATE_DRIVERS_FLAGS = 'recalculate_users_flags';
    public const OPTION_RECALCULATE_DRIVERS_BALANCE_HISTORY = 'recalculate_users_balance_history';

    public const OPTION_IMPORT_DATA = 'import_data';

    private const DEFAULT_OPTIONS = [
        self::OPTION_RECALCULATE_DRIVERS_FLAGS => false,
        self::OPTION_RECALCULATE_DRIVERS_BALANCE_HISTORY => false,
        self::OPTION_IMPORT_DATA => false,
    ];

    public function __construct(
        private readonly string $rootFolder,
        private readonly string $sourcesDir,
        private readonly string $reportsFolder
    ) {}

    public function run(array $options = self::DEFAULT_OPTIONS)
    {
        $logFile = $this->rootFolder . '/logs/mob828.log';
        $log = fopen($logFile, 'a');
        $logger = new StreamOutput($log);

        $consoleOutput = new ConsoleOutput();

        // Running the script requires some manual preparation to be done

        // 1. Make sure the previously imported sessions are removed and new data is imported
        // otherwise the script will work with old data and create wrong reports
        // Action: run this script with --import-data|-i option
        if ($this->importData($options)) {
            $this->runDataImport($logger, $consoleOutput);

            exit(0);
        }

        // 2. Prepare file with previously fixed drivers from migration
        // it's needed so the balance drop will not be considered as a replenishment
        // Action: create file in the source folder with comma separated list of driver id whose account where fixed
        // file name must be previously_fixed_drivers.txt

        // 3.

        $consoleOutput->writeln('Analyzer Mob407 v3 started');

        // Basic preparation does:
        // 1. creates aggregated table with drivers who has business sessions (for making calculations faster)
        // 2. inserts fake user payment log for drivers who have initial negative account balance for proper refund calculation
        $this->basicPreparation($logger, $consoleOutput);

        // Recalculating driver history creates payments history based on clb_user_payment_logs
        // payment history is just simplified version that suits better the current task
        if ($this->recalculateBalanceHistory($options)) {
            $this->runBalanceHistoryRecalculation($logger, $consoleOutput);
        }

        // Recalculating driver flags creates drivers table where each driver has the next flags:
        // 1. has_business_sessions
        // 2. has_personal_sessions
        // 3. has_income
        // 4. has_refunds
        // 5. is_affected
        // 7. previously fixed date
        if ($this->recalculateDriversFlags($options)) {
            $this->runDriversRecalculation($logger, $consoleOutput);
        }

        $consoleOutput->writeln('Generating report Group 1');
        Group1Report::init($logger, $consoleOutput, $this->reportsFolder . '/group1.csv')
            ->addExtraOutput(Group1Report::REPORT_NAME, $this->reportsFolder . '/group1_drivers.txt')
            ->generate();

        $consoleOutput->writeln('Generating report Group 2');
        Group2Report::init($logger, $consoleOutput, $this->reportsFolder . '/group2.csv')
            ->addExtraOutput(Group2Report::REPORT_NAME, $this->reportsFolder . '/group2_drivers.txt')
            ->generate();

        $consoleOutput->writeln('Generating report Group 3');
        Group3Report::init($logger, $consoleOutput, $this->reportsFolder . '/group3.csv')
            ->addExtraOutput(Group3Report::REPORT_NAME, $this->reportsFolder . '/group3_drivers.txt')
            ->generate();

        $consoleOutput->writeln('Generating report of drivers with refunds');
        GroupWithRefundsReport::init($logger, $consoleOutput, $this->reportsFolder . '/drivers_with_refunds.csv')
            ->addExtraOutput(GroupWithRefundsReport::REPORT_NAME, $this->reportsFolder . '/drivers_with_refunds.txt')
            ->generate();

        $consoleOutput->writeln('Verification of covered cases');
        Verifier::init($logger, $consoleOutput)
            ->addSourceFile(Group1Report::REPORT_NAME, $this->reportsFolder . '/group1_drivers.txt')
            ->addSourceFile(Group2Report::REPORT_NAME, $this->reportsFolder . '/group2_drivers.txt')
            ->addSourceFile(Group3Report::REPORT_NAME, $this->reportsFolder . '/group3_drivers.txt')
            ->addSourceFile(GroupWithRefundsReport::REPORT_NAME, $this->reportsFolder . '/drivers_with_refunds.txt')
            ->verify();


        fclose($log);
    }

    private function basicPreparation(Output $logger, Output $consoleOutput): void
    {
        $consoleOutput->writeln(' - Creating users with business sessions');
        CreateDriversWithBusinessSessionsTableTask::init($logger, $consoleOutput)->run();

        $consoleOutput->writeln(' - Generate fake 0.0 payment logs for drivers whose balance starts with negative');
        GenerateFakeZeroPaymentLogsTask::init($logger, $consoleOutput)
            ->addSourceFile('starts_with_negative', $this->sourcesDir . '/starts_with_negative_drivers_list.txt')
            ->run();

        $consoleOutput->writeln(' - Done');
    }

    private function recalculateDriversFlags(array $options): bool
    {
        return $options[self::OPTION_RECALCULATE_DRIVERS_FLAGS] ?? false;
    }

    private function runDriversRecalculation(Output $logger, Output $consoleOutput): void
    {
        $consoleOutput->writeln('Recalculating drivers flags');
        $consoleOutput->writeln(' - Creating/recreating drivers table...');

        CreateDriversTableTask::init($logger, $consoleOutput)->run();

        $consoleOutput->writeln(' - Done');
        $consoleOutput->writeln(' - Inserting in drivers from details file...');

        InsertDriversFromDetailsFileTask::init($logger, $consoleOutput)
            ->addSourceFile('driver_details', $this->sourcesDir . '/driver_details.json')
            ->addSourceFile('organizations', $this->sourcesDir . '/organizations.json')
            ->addSourceFile('previously_fixed_group1', $this->sourcesDir . '/previously_fixed_group1.txt')
            ->addExtraOutput('starts_with_negative', $this->reportsFolder . '/starts_with_negative.txt')
            ->addExtraOutput('verify_manually', $this->reportsFolder . '/verify_manually.txt')
            ->addExtraOutput('has_no_business_sessions', $this->reportsFolder . '/has_no_business_sessions.txt')
            ->addExtraOutput('is_not_affected', $this->reportsFolder . '/is_not_affected.txt')
            ->run();

        $consoleOutput->writeln(' - Done');
        $consoleOutput->writeln(' - Inserting drivers with business sessions...');

        InsertDriversWithBusinessSessionsTask::init($logger, $consoleOutput)
            ->addSourceFile('organizations', $this->sourcesDir . '/organizations.csv')
            ->addSourceFile('previously_fixed_group1', $this->sourcesDir . '/previously_fixed_group1.txt')
            ->addExtraOutput('starts_with_negative', $this->reportsFolder . '/starts_with_negative.txt')
            ->addExtraOutput('verify_manually', $this->reportsFolder . '/verify_manually.txt')
            ->run();

        $consoleOutput->writeln(' - Done');
    }

    private function recalculateBalanceHistory(array $options): bool
    {
        return $options[self::OPTION_RECALCULATE_DRIVERS_BALANCE_HISTORY] ?? false;
    }

    private function importData(array $options): bool
    {
        return $options[self::OPTION_IMPORT_DATA] ?? false;
    }

    private function runBalanceHistoryRecalculation(Output $logger, Output $consoleOutput): void
    {
        $consoleOutput->writeln('Recalculating drivers balance history');
        $consoleOutput->writeln(' - Creating/recreating balance_history table...');

        CreateBalanceHistoryTableTask::init($logger, $consoleOutput)->run();

        $consoleOutput->writeln(' - Done');
        $consoleOutput->writeln(' - Inserting drivers balance history...');

        InsertBalanceHistoryTask::init($logger, $consoleOutput)
            ->addExtraOutput('no_payment_drivers', $this->reportsFolder . '/no_payment_drivers.txt')
            ->addExtraOutput('personal_charged', $this->reportsFolder . '/personal_charged.txt')
            ->addExtraOutput('null_amount', $this->reportsFolder . '/null_amount.txt')
            ->addExtraOutput('purchase_card', $this->reportsFolder . '/purchase_card.txt')
            ->run();

        $consoleOutput->writeln(' - Done');
    }

    private function runDataImport(Output $logger, Output $output): void
    {
        $output->writeln('Importing data');

        CreateCoulombTablesTask::init($logger, $output)->run();

        ImportCoulombSessionsTask::init($logger, $output)
            ->addSourceFile(
                ImportCoulombSessionsTask::SOURCE_CLB_EXTERNAL_VEHICLE_CHARGE . '_1',
                $this->sourcesDir . '/mob828_external_vehicle_charge_1.json'
            )
            ->addSourceFile(
                ImportCoulombSessionsTask::SOURCE_CLB_EXTERNAL_VEHICLE_CHARGE . '_2',
                $this->sourcesDir . '/mob828_external_vehicle_charge_2.json'
            )
            ->addSourceFile(
                ImportCoulombSessionsTask::SOURCE_CLB_EXTERNAL_VEHICLE_CHARGE . '_3',
                $this->sourcesDir . '/mob828_external_vehicle_charge_3.json'
            )
            ->addSourceFile(
                ImportCoulombSessionsTask::SOURCE_CLB_EXTERNAL_VEHICLE_CHARGE . '_4',
                $this->sourcesDir . '/mob828_external_vehicle_charge_4.json'
            )
            ->addSourceFile(
                ImportCoulombSessionsTask::SOURCE_CLB_EXTERNAL_VEHICLE_CHARGE . '_5',
                $this->sourcesDir . '/mob828_external_vehicle_charge_5.json'
            )
            ->addSourceFile(
                ImportCoulombSessionsTask::SOURCE_CLB_EXTERNAL_VEHICLE_CHARGE_EXT . '_1',
                $this->sourcesDir . '/mob828_external_vehicle_charge_ext_1.json'
            )
            ->addSourceFile(
                ImportCoulombSessionsTask::SOURCE_CLB_EXTERNAL_VEHICLE_CHARGE_EXT . '_2',
                $this->sourcesDir . '/mob828_external_vehicle_charge_ext_2.json'
            )
            ->addSourceFile(
                ImportCoulombSessionsTask::SOURCE_CLB_EXTERNAL_VEHICLE_CHARGE_EXT . '_3',
                $this->sourcesDir . '/mob828_external_vehicle_charge_ext_3.json'
            )
            ->addSourceFile(
                ImportCoulombSessionsTask::SOURCE_CLB_EXTERNAL_VEHICLE_CHARGE_EXT . '_4',
                $this->sourcesDir . '/mob828_external_vehicle_charge_ext_4.json'
            )
            ->addSourceFile(
                ImportCoulombSessionsTask::SOURCE_CLB_EXTERNAL_VEHICLE_CHARGE_EXT . '_5',
                $this->sourcesDir . '/mob828_external_vehicle_charge_ext_5.json'
            )
            ->run();

        ImportUserPaymentLogTask::init($logger, $output)
            ->addSourceFile(
                ImportUserPaymentLogTask::SOURCE_CLB_USER_PAYMENT_LOG . "_1",
                $this->sourcesDir . "/mob828_user_payment_log_1.json"
            )
            ->addSourceFile(
                ImportUserPaymentLogTask::SOURCE_CLB_USER_PAYMENT_LOG . "_2",
                $this->sourcesDir . "/mob828_user_payment_log_2.json"
            )
            ->addSourceFile(
                ImportUserPaymentLogTask::SOURCE_CLB_USER_PAYMENT_LOG . "_3",
                $this->sourcesDir . "/mob828_user_payment_log_3.json"
            )
            ->addSourceFile(
                ImportUserPaymentLogTask::SOURCE_CLB_USER_PAYMENT_LOG . "_4",
                $this->sourcesDir . "/mob828_user_payment_log_4.json"
            )
            ->addSourceFile(
                ImportUserPaymentLogTask::SOURCE_CLB_USER_PAYMENT_LOG . "_5",
                $this->sourcesDir . "/mob828_user_payment_log_5.json"
            )
            ->addSourceFile(
                ImportUserPaymentLogTask::SOURCE_CLB_USER_PAYMENT_LOG . "_6",
                $this->sourcesDir . "/mob828_user_payment_log_6.json"
            )
            ->addSourceFile(
                ImportUserPaymentLogTask::SOURCE_CLB_USER_PAYMENT_LOG . "_7",
                $this->sourcesDir . "/mob828_user_payment_log_7.json"
            )
            ->run();
    }
}
