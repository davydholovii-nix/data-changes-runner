<?php

namespace App\Mob407\V3\Reports;

use App\Mob407\V3\Models\Driver;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Symfony\Component\Console\Output\Output;

class GroupWithRefundsReport extends AbstractReport
{
    public const REPORT_NAME = 'group_with_refunds_drivers';
    public static function init(Output $logger, Output $output, string $reportFile): static
    {
        return new static(self::REPORT_NAME, $logger, $output, $reportFile);
    }

    protected function getQuery(): QueryBuilder|EloquentBuilder
    {
        return Driver::query()
            ->where('has_refunds', 1);
    }
}
