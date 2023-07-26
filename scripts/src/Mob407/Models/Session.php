<?php

namespace App\Mob407\Models;

use App\Mob407\Models\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read SessionDetails $details
 * @property-read PaymentLog $paymentLog
 */
class Session extends Model {
    protected $table = 'external_vehicle_charge';

    public function details() {
        return $this->hasOne(SessionDetails::class, 'evc_id', 'id');
    }

    public function paymentLog()
    {
        return $this->hasOne(PaymentLog::class, 'vc_id', 'id');
    }

    public function isBusiness() {
        return $this->details?->transaction_type == TransactionType::BUSINESS;
    }
}
