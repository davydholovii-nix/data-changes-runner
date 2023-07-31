<?php

namespace App\Driser126\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property int $user_id
 * @property string $email
 */
class UserLogin extends Model
{
    protected $table = 'user_login';

    protected $connection = \Env::QA->value;
}
