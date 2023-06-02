<?php

namespace App\Mob407;

use App\Mob407\V3\Tasks\CreateBalanceHistoryTableTask;
use App\Mob407\V3\Tasks\CreateDriversTableTask;
use App\Mob407\V3\Tasks\CreateDriversWithBusinessSessionsTableTask;
use App\Mob407\V3\Tasks\InsertBalanceHistoryTask;
use App\Mob407\V3\Tasks\InsertDriversFromDetailsFileTask;
use App\Mob407\V3\Tasks\InsertDriversWithBusinessSessionsTask;
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
        $log = fopen($logFile, 'w');
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

        fclose($log);
    }

    private function basicPreparation(Output $logger, Output $consoleOutput): void
    {
        $consoleOutput->writeln(' - Creating users with business sessions');
        CreateDriversWithBusinessSessionsTableTask::init($logger, $consoleOutput)->run();

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
            ->addExtraOutput('starts_with_negative', $this->reportsFolder . '/starts_with_negative.txt')
            ->addExtraOutput('verify_manually', $this->reportsFolder . '/verify_manually.txt')
            ->run();

        $consoleOutput->writeln(' - Done');
        $consoleOutput->writeln(' - Inserting drivers with business sessions...');

        InsertDriversWithBusinessSessionsTask::init($logger, $consoleOutput)
            ->addSourceFile('organizations', $this->sourcesDir . '/organizations.csv')
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
