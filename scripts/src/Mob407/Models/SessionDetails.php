<?php

namespace App\Mob407\Models;

use App\Mob407\Models\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property TransactionType $transaction_type
 */
class SessionDetails extends Model
{
    protected $table = 'external_vehicle_charge_ext';

    protected $casts = [
        'transaction_type' => TransactionType::class,
    ];
}
