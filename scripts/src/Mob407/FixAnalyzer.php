<?php

namespace App\Mob407;

use App\Mob407\Actions\CheckAlreadyResetDrivers;
use App\Mob407\Actions\PrepareDriversTable;
use App\Mob407\Actions\PreparePaymentHistoryTable;
use App\Mob407\Actions\CheckDriversBusinessOnlyToRefund;
use App\Mob407\Actions\CheckDriversPersonalOnlyToRefund;
use App\Mob407\Actions\CheckDriversPersonalToResetPaidAndRefund;
use App\Mob407\Actions\CheckDriversToResetBalance;
use App\Mob407\Actions\CheckDriversBusinessToResetPaidAndRefund;
use App\Mob407\Models\Driver;
use Symfony\Component\Console\Output\ConsoleOutput;

class FixAnalyzer
{
    private ConsoleOutput $output;

    public function __construct(private readonly string $rootPath)
    {
        $this->output = new ConsoleOutput();
    }

    public function run(array $options = [])
    {
        [$reportFolder, $migrationsFolder] = $this->createFolders();

        $this->output->writeln("Step 1: Prepare users and who have business sessions and are affected by MOB-407");
        PrepareDriversTable::run($this->output, in_array('force_recreate_users_table', $options));

        $this->output->writeln("Step 2: Create payments history based on user_payment_log");
        PreparePaymentHistoryTable::run($this->output, in_array('force_recreate_payments_history_table', $options));

        $this->output->writeln("Step 3: Create report of already fixed drivers with no issues after");
        CheckAlreadyResetDrivers::run($this->output, $reportFolder);

        $this->output->writeln("Step 4: Create migration and report of drivers whose balance has to be reset to 0 only");
        CheckDriversToResetBalance::run($this->output, $reportFolder, $migrationsFolder);

        $this->output->writeln("Step 5: Create report of drivers with only business sessions who have to be refunded only");
        CheckDriversBusinessOnlyToRefund::run($this->output, $reportFolder);

        $this->output->writeln("Step 6: Create report of the rest of the drivers with only business sessions");
        $this->output->writeln("Note: Check the drivers if they had payment method attached");
        $this->output->writeln("      Yes: Restore paid account, reset balance to 0 and refund if needed");
        $this->output->writeln("      NO:  Reset balance to 0");
        CheckDriversBusinessToResetPaidAndRefund::run($this->output, $reportFolder);

        $this->output->writeln("Step 7: Create report of drivers with personal sessions and negative balance");
        CheckDriversPersonalToResetPaidAndRefund::run($this->output, $reportFolder);

        $this->output->writeln("Step 8: Create report of drivers with personal to refund only");
        CheckDriversPersonalOnlyToRefund::run($this->output, $reportFolder);

        $this->output->writeln("");
        $this->output->writeln("Verification:");
        $this->output->writeln(sprintf(
            ' + Expected %d drivers use cases to be covered',
            Driver::query()->affected()->count()
        ));

        $alreadyReset = CheckAlreadyResetDrivers::query()->count();
        $driversToResetBalanceOnly = CheckDriversToResetBalance::query()->count();
        $driversToRefundOnly = CheckDriversBusinessOnlyToRefund::query()->count();
        $driversToResetPaidAndRefund = CheckDriversBusinessToResetPaidAndRefund::query()->count();
        $driversPersonalToResetPaidAndRefund = CheckDriversPersonalToResetPaidAndRefund::query()->count();
        $driversPersonalToRefund = CheckDriversPersonalOnlyToRefund::query()->count();

        $this->output->writeln(sprintf(
            ' + %d drivers actually covered',
            $alreadyReset
            + $driversToResetBalanceOnly
            + $driversToRefundOnly
            + $driversToResetPaidAndRefund
            + $driversPersonalToResetPaidAndRefund
            + $driversPersonalToRefund
        ));

        // Check if IDs overlap
        $this->checkIdsOverlap();

        // Not covered drivers
        $this->notCoveredDrivers();
    }

    private function checkIdsOverlap(): void
    {
        $alreadyReset = CheckAlreadyResetDrivers::query()->select('id')->pluck('id')->toArray();
        $driversToResetBalanceOnly = CheckDriversToResetBalance::query()->select('id')->pluck('id')->toArray();
        $driversToRefundOnly = CheckDriversBusinessOnlyToRefund::query()->select('id')->pluck('id')->toArray();
        $driversToResetPaidAndRefund = CheckDriversBusinessToResetPaidAndRefund::query()->select('id')->pluck('id')->toArray();
        $driversPersonalToResetPaidAndRefund = CheckDriversPersonalToResetPaidAndRefund::query()->select('id')->pluck('id')->toArray();
        $driversPersonalToRefund = CheckDriversPersonalOnlyToRefund::query()->select('id')->pluck('id')->toArray();

        $ids = array_merge(
            $alreadyReset,
            $driversToResetBalanceOnly,
            $driversToRefundOnly,
            $driversToResetPaidAndRefund,
            $driversPersonalToResetPaidAndRefund,
            $driversPersonalToRefund
        );

        $uniqueIds = array_unique($ids);

        if (count($ids) !== count($uniqueIds)) {
            $this->output->writeln(' - IDs overlap detected');
        } else {
            $this->output->writeln(' - No IDs overlap detected');
        }
    }

    private function notCoveredDrivers(): void
    {
        $affected = Driver::query()->affected()->select('id')->pluck('id')->toArray();
        $alreadyReset = CheckAlreadyResetDrivers::query()->select('id')->pluck('id')->toArray();
        $driversToResetBalanceOnly = CheckDriversToResetBalance::query()->select('id')->pluck('id')->toArray();
        $driversToRefundOnly = CheckDriversBusinessOnlyToRefund::query()->select('id')->pluck('id')->toArray();
        $driversToResetPaidAndRefund = CheckDriversBusinessToResetPaidAndRefund::query()->select('id')->pluck('id')->toArray();
        $driversPersonalToResetPaidAndRefund = CheckDriversPersonalToResetPaidAndRefund::query()->select('id')->pluck('id')->toArray();
        $driversPersonalToRefund = CheckDriversPersonalOnlyToRefund::query()->select('id')->pluck('id')->toArray();

        $notCovered = array_diff($affected, $alreadyReset, $driversToResetBalanceOnly, $driversToRefundOnly, $driversToResetPaidAndRefund, $driversPersonalToResetPaidAndRefund, $driversPersonalToRefund);

        if (count($notCovered) > 0) {
            $this->output->writeln(' - Not covered drivers detected');
            $this->output->writeln(' - ' . implode(', ', $notCovered));
        } else {
            $this->output->writeln(' - No not covered drivers detected');
        }
    }

    private function createFolders(): array
    {
        $mainFolder = $this->rootPath . '/var/mob407';

        if (!file_exists($mainFolder)) {
            mkdir(directory: $mainFolder, recursive: true);
        }

        $timestamp = date('YmdHis');

        $report = $mainFolder . '/' . $timestamp;

        if (!file_exists($report)) {
            mkdir(directory: $report, recursive: true);
        }

        $migrations = $report . '/migrations';

        if (!file_exists($migrations)) {
            mkdir(directory: $migrations, recursive: true);
        }

        return [$report, $migrations];
    }
}