<?php

namespace App\Mob407;

use App\Mob407\V3\Reports\Group1Report;
use App\Mob407\V3\Reports\Group2Report;
use App\Mob407\V3\Reports\Group3Report;
use App\Mob407\V3\Reports\GroupWithRefundsReport;
use App\Mob407\V3\Tasks\CreateBalanceHistoryTableTask;
use App\Mob407\V3\Tasks\CreateDriversTableTask;
use App\Mob407\V3\Tasks\CreateDriversWithBusinessSessionsTableTask;
use App\Mob407\V3\Tasks\GenerateFakeZeroPaymentLogsTask;
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

    private const DEFAULT_OPTIONS = [
        self::OPTION_RECALCULATE_DRIVERS_FLAGS => false,
        self::OPTION_RECALCULATE_DRIVERS_BALANCE_HISTORY => false,
    ];

    public function __construct(
        private readonly string $rootFolder,
        private readonly string $sourcesDir,
        private readonly string $reportsFolder
    ) {}

    public function run(array $options = self::DEFAULT_OPTIONS)
    {
        $logFile = $this->rootFolder . '/logs/mob407.log';
        $log = fopen($logFile, 'a');
        $logger = new StreamOutput($log);

        $consoleOutput = new ConsoleOutput();
        $consoleOutput->writeln('Analyzer Mob407 v3 started');

        $this->basicPreparation($logger, $consoleOutput);

        if ($this->recalculateBalanceHistory($options)) {
            $this->runBalanceHistoryRecalculation($logger, $consoleOutput);
        }

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
            ->addSourceFile('driver_details', $this->sourcesDir . '/driver_details.csv')
            ->addSourceFile('organizations', $this->sourcesDir . '/organizations.csv')
            ->addSourceFile('previously_fixed_group1', $this->sourcesDir . '/previously_fixed_group1.txt')
            ->addExtraOutput('starts_with_negative', $this->reportsFolder . '/starts_with_negative.txt')
            ->addExtraOutput('verify_manually', $this->reportsFolder . '/verify_manually.txt')
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

}
