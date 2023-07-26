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
use App\Mob407\Actions\V2\CreateDriverDetails;
use App\Mob407\Actions\V2\CreateGroup2Report;
use App\Mob407\Actions\V2\CreateGroup3Report;
use App\Mob407\Actions\V2\CreateOrganizations;
use App\Mob407\Models\Driver;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;

class FixAnalyzerV2
{
    private ConsoleOutput $output;

    public function __construct(private readonly string $rootPath, private readonly string $sourcesDir)
    {
        $this->output = new ConsoleOutput();
    }

    public function run(array $options = [])
    {
        $reportFolder = $this->createFolders();

        $this->output->writeln('1. Create organizations');
        CreateOrganizations::run($this->output, $this->sourcesDir . '/organizations.csv');
        $this->output->writeln('2. Create driver details');
        CreateDriverDetails::run($this->output, $this->sourcesDir . '/driver_details.csv');
        $this->output->writeln('3. Create group 2 drivers report');
        CreateGroup2Report::run($this->output, $reportFolder);
        $this->output->writeln('4. Create group 3 drivers report');
        CreateGroup3Report::run($this->output, $reportFolder);

        $this->output->writeln('See the report in ' . $reportFolder);
    }

    private function createFolders(): string
    {
        $mainFolder = $this->rootPath . '/var/mob407/June1';

        if (!file_exists($mainFolder)) {
            mkdir(directory: $mainFolder, recursive: true);
        }

        $timestamp = date('YmdHis');

        $report =  $mainFolder . '/' . 'v2_' .$timestamp;

        if (!file_exists($report)) {
            mkdir(directory: $report, recursive: true);
        }

        return $report;
    }
}
