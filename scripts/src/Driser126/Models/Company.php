<?php

namespace App\Driser126\Models;

/**
 * @property-read int $id
 * @property-read string $organization_id
 */
class Company extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'company';

    protected $connection = \Env::QA->value;
}
