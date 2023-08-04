<?php

namespace App\Driser126\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read int $driver_id
 * @property-read int $company_id
 * @property-read int $connection_id
 * @property-read bool $has_home_requested
 */
class DriverAffiliation extends Model
{
    protected $table = 'company_driver_affiliation';

    protected $connection = \Env::LocalCoulomb->value;

    protected $casts = [
        'has_home_requested' => 'bool'
    ];
}
