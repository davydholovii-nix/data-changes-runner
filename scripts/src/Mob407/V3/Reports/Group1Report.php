<?php

namespace App\Mob407\V3\Reports;

use App\Mob407\V3\Models\Driver;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Console\Output\Output;

class Group1Report extends AbstractReport
{
    public const REPORT_NAME = 'group1_drivers';

    public static function init(Output $logger, Output $output, string $reportFile): static
    {
        return new static(self::REPORT_NAME, $logger, $output, $reportFile);
    }

    protected function getAmountToRefund(int $driverId): float
    {
        return 0.0;
    }

    protected function getQuery(): Builder
    {
        return Driver::query()
            ->where('is_affected', 1)
            ->where('balance', '<', 0)
            ->where('has_personal_sessions', 0)
            ->where('has_income', 0)
            ->where('has_refunds', 0);
    }
}
