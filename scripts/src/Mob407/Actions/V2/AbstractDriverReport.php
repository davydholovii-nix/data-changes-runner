<?php

namespace App\Mob407\Actions\V2;

use App\Mob407\Models\Driver;
use App\Mob407\Models\Enums\TransactionType;
use App\Mob407\Models\Session;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

abstract class AbstractDriverReport
{
    protected static function getAffectedSessionsQuery(Driver $driver): EloquentBuilder
    {
        return Session::query()
            ->join('external_vehicle_charge_ext', 'external_vehicle_charge_ext.evc_id', '=', 'external_vehicle_charge.id')
            ->where('external_vehicle_charge_ext.transaction_type', TransactionType::BUSINESS)
            ->whereIn('external_vehicle_charge.id', function (QueryBuilder $query) use ($driver) {
                $query->from('payments_history')
                    ->select('payments_history.session_id')
                    ->where('payments_history.user_id', $driver->id)
                    ->where('payments_history.balance_diff', '<', 0);
            });
    }
}
