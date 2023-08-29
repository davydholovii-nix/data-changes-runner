<?php

namespace App\Mob407\V3\Reports;

use App\Mob407\V3\Helpers\HasExtraOutput;
use App\Mob407\V3\Helpers\HasLogger;
use App\Mob407\V3\Helpers\Progress;
use App\Mob407\V3\Models\Driver;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\StreamOutput;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

abstract class AbstractReport
{
    use HasLogger;
    use HasExtraOutput;

    protected ?Output $reportOutput = null;

    protected function __construct(
        private readonly string $reportName,
        private readonly Output $logger,
        private readonly Output $output,
        private readonly string $reportFile,
    ) {}

    public function __destruct()
    {
        if ($this->reportOutput) {
            fclose($this->reportOutput->getStream());
        }
    }

    abstract public static function init(Output $logger, Output $output, string $reportFile): static;

    abstract protected function getQuery(): QueryBuilder|EloquentBuilder;

    public function generate(): void
    {
        $progress = Progress::init($this->getOutput(), $this->getQuery()->count());

        $summary = $this->initialSummary();

        $this->getQuery()
            ->chunk(100, function ($drivers) use ($progress, &$summary) {
                $summary = $this->summaryWithUpdated($summary, 'drivers', $drivers->count());

                foreach ($drivers as $driver) {
                    $progress->advance();
                    $this->writeExtra($this->reportName, $driver->id . ','); // Save in file to verify overlap

                    [$amountToRefund, $countAffectedSessions] = $this->reportDriver($driver);

                    if (!$countAffectedSessions) {
                        throw new \RuntimeException('Unhandled use case ' .  $driver->id);
                    }

                    $summary = $this->summaryWithUpdated($summary, 'amount', $amountToRefund);
                    $summary = $this->summaryWithUpdated($summary, 'affectedSessions', $countAffectedSessions);
                }
            });

        $progress->finish(clean: true);

        $this->getOutput()->writeln('  Drivers affected: ' . $summary['drivers']);
        $this->getOutput()->writeln('  Sessions affected: ' . $summary['affectedSessions']);
        $this->getOutput()->writeln('  Total amount to refund: ' . $summary['amount']);
    }

    protected function initialSummary(): array
    {
        return [
            'drivers' => 0,
            'amount' => 0.0,
            'affectedSessions' => 0,
        ];
    }

    protected function summaryWithUpdated(array $summary, string $key, int|float $value): array
    {
        $summary[$key] = $summary[$key] + $value;

        return $summary;
    }

    protected function getLogger(): Output
    {
        return $this->logger;
    }

    protected function getOutput(): Output
    {
        return $this->output;
    }

    protected function getReportOutput(): Output
    {
        if ($this->reportOutput instanceof Output) {
            return $this->reportOutput;
        }

        $stream = fopen($this->reportFile, 'w');
        if (!$stream) {
            throw new \RuntimeException('Cannot open report file ' . $this->reportFile . ' for writing');
        }
        $this->reportOutput = new StreamOutput($stream);
        $this->reportOutput->writeln(implode(",", [
            $this->csvWrap('Driver ID'),
            $this->csvWrap('Amount to refund'),
            $this->csvWrap('Email'),
            $this->csvWrap('Notify email'),
            $this->csvWrap("First name"),
            $this->csvWrap("Last name"),
            $this->csvWrap('Middle name'),
            $this->csvWrap('Country'),
            $this->csvWrap('Country code'),
            $this->csvWrap('Language'),
            $this->csvWrap('Org ID'),
            $this->csvWrap('Affected sessions'),
        ]));

        return $this->reportOutput;
    }

    protected function reportDriver(Driver $driver): array
    {
        $amountToRefund = $this->getAmountToRefund($driver->id);
        $affectedSessions = $this->getAffectedSessions($driver->id);

        $this->getReportOutput()
            ->writeln(implode(",", [
                $driver->id,
                $amountToRefund,
                $this->csvWrap($driver->email),
                !empty($driver->notify_email)
                    ? $this->csvWrap($driver->notify_email)
                    : '',
                $this->csvWrap($driver->first_name),
                $this->csvWrap($driver->last_name),
                $this->csvWrap($driver->middle_name),
                $this->csvWrap($driver->country_name),
                $this->csvWrap($driver->country_code),
                $this->csvWrap($driver->pref_lang),
                $this->csvWrap($driver->org_code),
                $this->csvWrap(implode(',', $affectedSessions)),
            ]));

        return [$amountToRefund, count($affectedSessions)];
    }

    protected function getAmountToRefund(int $driverId): float
    {
        $sql = "
            SELECT SUM(amount) AS amount_to_refund
            FROM clb_balance_history
            WHERE driver_id = :driverId
              AND balance_diff < 0
              AND is_business = 1
              AND type = 19
        ";

        $result = DB::select($sql, ['driverId' => $driverId]);

        if (empty($result) || is_null(current($result)->amount_to_refund)) {
            throw new \RuntimeException('No balance history for driver ' . $driverId);
        }

        return current($result)->amount_to_refund;
    }

    protected function getAffectedSessions(int $driverId): array
    {
        return DB::table('balance_history')
            ->where('driver_id', $driverId)
            ->where('balance_diff', '<', 0)
            ->where('is_business', 1)
            ->get('session_id')
            ->pluck('session_id')
            ->toArray();
    }

    private function csvWrap(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
