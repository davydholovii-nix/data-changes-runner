<?php

namespace App\Driser126\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property int $driver_id
 * @property int $company_id
 * @property int $connection_id
 * @property int $driver_group_id
 * @property string $leaseco_org_id
 * @property string $leaseco_org_name
 * @property string $currency_iso_code
 * @property string $connection_name
 * @property string $driver_first_name
 * @property string $driver_last_name
 * @property string $request_status
 * @property bool $charger_deactivated
 * @property string $email
 * @property string $phone_number
 * @property string $dialing_code
 * @property string $address1
 * @property string $address2
 * @property string $zip_code
 * @property string $city
 * @property int $country_id
 * @property string $country_code
 * @property string $country_name
 * @property int $state_id
 * @property string $state_code
 * @property string $state_name
 * @property string $created_at
 * @property string $updated_at
 */
class HomeChargerRequest extends Model
{
    protected $table = 'home_charger_requests';

    protected $connection = \Env::LocalDms->value;

    protected $casts = [
        'charger_deactivated' => 'bool',
    ];
}
