<?php

namespace App\Mob407\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $driver_id
 * @property int $org_id
 * @property string $email
 * @property string $notify_email
 * @property string $first_name
 * @property string $last_name
 * @property string $middle_name
 * @property string $country_name
 * @property string $country_code
 * @property string $lang
 *
 * * @property-read Organization $org
 */
class DriverDetails extends Model {
    protected $table = 'driver_details';

    public $timestamps = false;

    public function org(): HasOne
    {
        return $this->hasOne(Organization::class, 'id', 'org_id');
    }
}
