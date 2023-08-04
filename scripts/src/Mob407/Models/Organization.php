<?php

namespace App\Mob407\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $nam
 * @property string $code
 */
class Organization extends Model {
    protected $table = 'organizations';

    public $timestamps = false;
}
