<?php

namespace App\Mob407\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $session_id
 * @property int $payment_type
 * @property float $amount
 * @property float $balance_diff
 * @property float $balance_before
 * @property float $balance_after
 * @property string $created_at
 *
 * @property-read Session|null $session
 * @property-read Driver $driver
 */
class PaymentHistory extends Model {
    protected $table = 'payments_history';

    public $timestamps = false;

    protected $casts = [
        'user_id' => 'int',
        'session_id' => 'int',
        'payment_type' => 'int',
        'balance_diff' => 'float',
        'balance_before' => 'float',
        'balance_after' => 'float',
    ];

    public function session() {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }

    public function driver() {
        return $this->belongsTo(Driver::class, 'user_id', 'id');
    }
}