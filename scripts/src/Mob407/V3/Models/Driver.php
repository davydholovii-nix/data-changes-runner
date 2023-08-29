<?php

namespace App\Mob407\V3\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string $email
 * @property string $notify_email
 * @property string $country_name
 * @property string $country_code
 * @property string $pref_lang
 * @property string $org_code
 *
 * @property float $balance
 * @property bool $is_affected
 * @property bool $has_business_sessions
 * @property bool $has_personal_sessions
 * @property bool $has_income
 * @property bool $has_refunds
 * @property null|string $previously_fixed_at
 */
class Driver extends Model
{
    public $timestamps = false;

    protected $table = 'drivers';

    protected $casts = [
        'balance' => 'float',
        'is_affected' => 'bool',
        'has_business_sessions' => 'bool',
        'has_personal_sessions' => 'bool',
        'has_income' => 'bool',
        'has_refunds' => 'bool',
    ];
}
