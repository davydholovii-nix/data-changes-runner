<?php

namespace App\Mob407\V3\Reports;

use App\Mob407\V3\Models\Driver;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Console\Output\Output;

class Group2Report extends AbstractReport
{
    public const REPORT_NAME = 'group2_drivers';

    public static function init(Output $logger, Output $output, string $reportFile): static
    {
        return new static(self::REPORT_NAME, $logger, $output, $reportFile);
    }

    protected function getQuery(): Builder
    {
        /*
         SELECT driver_id
         FROM clb_drivers
         WHERE is_affected = 1
            AND (has_income = 1 OR has_personal_sessions = 1 OR balance > 0)
            AND has_refunds = 0
            AND balance >= 0
        */
        return Driver::query()
            ->where('is_affected', 1)
            ->where(function (Builder $query) {
                $query->where('has_income', 1)
                    ->orWhere('has_personal_sessions', 1)
                    ->orWhere('balance', '>', 0);
            })
            ->where('has_refunds', 0)
            ->where('balance', '>=', 0);
    }
}
