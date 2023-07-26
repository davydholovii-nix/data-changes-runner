<?php

namespace App\Mob407\Models;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class to represent a user payment log entry.
 *
 * @property int $id The unique identifier of the payment log entry.
 * @property float $amount The amount of the payment.
 * @property float $account_balance The user's account balance after the payment was made.
 * @property int $type The type of payment. One of:
 * @property int|null $subtype The subtype of payment. Used in conjunction with the type field to provide additional detail. Possible values depend on the value of the type field.
 * @property int $status The status of the payment. One of:
 * @property int $transaction_status The status of the transaction. One of:
 * @property int|null $user_id The ID of the user associated with the payment.
 * @property int|null $admin_id The ID of the administrator who processed the payment.
 * @property int $user_type The type of user associated with the payment. One of:
 * @property string|null $description A description of the payment.
 * @property string $create_date The date and time the payment was created.
 * @property int|null $vc_id The ID of the virtual card used for the payment.
 * @property int|null $reservation_id The ID of the reservation associated with the payment.
 * @property int|null $merchant_id The ID of the payment merchant. One of:
 * @property string|null $request_id The ID of the payment request.
 * @property string|null $request_token The payment request token.
 * @property int|null $secured_server_reference_id The ID of the secured server reference.
 * @property string|null $logged_by The user who refunded the payment amount.
 * @property int $is_cron_processed Whether the payment has been processed by a cron job. One of:
 * @property int|null $reference_id The ID of the reference.
 * @property float|null $total_amount The total amount of the payment.
 * @property float|null $nos
 * @property-read Session $session
 * @method static EloquentBuilder byUser(int $userId)
 */
class PaymentLog extends Model {

    const TYPE_ROAMING_SESSION = 19;

    protected $table = 'user_payment_log';

    public function session() {
        return $this->belongsTo(Session::class, 'vc_id', 'id')->with('details');
    }

    public function isIncome(): bool
    {
        if ($this->type == 1 && $this->amount > 0 && $this->status == 1) {
            return true;
        }

        if ($this->type == 4 && $this->amount < 0 && $this->status == 1) {
            return true;
        }

        return false;
    }
}
