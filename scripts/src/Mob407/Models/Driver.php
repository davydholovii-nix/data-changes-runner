<?php

namespace App\Mob407\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property bool $has_personal_sessions
 * @property bool $has_business_sessions
 * @property bool $is_affected
 * @property float $balance
 *
 * @property-read PaymentHistory[]|\Illuminate\Database\Eloquent\Collection $payments
 *
 * @method static self|Builder affected()
 * @method static self|Builder hasOnlyBusinessSessions()
 * @method static self|Builder hasPersonalSessions()
 * @method static self|Builder hasNoPaymentMethod()
 * @method static self|Builder hasPaymentMethod()
 */
class Driver extends Model {
    protected $table = 'users';

    public $timestamps = false;

    protected $casts = [
        'has_personal_sessions' => 'bool',
        'has_business_sessions' => 'bool',
        'is_affected' => 'bool',
        'balance' => 'float',
    ];

    /**
     * @return self|Builder
     */
    public static function query()
    {
        return parent::query()
            ->whereNot('id', 0)
            ->whereNotNull('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentHistory::class, 'user_id', 'id');
    }

    public function hasTopUps(): bool
    {
        return $this
            ->payments()
            ->where('balance_diff', '>', 0)
            ->exists();
    }

    public function scopeAffected(Builder $query): Builder
    {
        return $query->where('is_affected', true);
    }

    public function scopeHasOnlyBusinessSessions(Builder $query): Builder
    {
        return $query
            ->where('has_business_sessions', true)
            ->where('has_personal_sessions', false);
    }

    public function scopeHasPersonalSessions(Builder $query): Builder
    {
        return $query->where('has_personal_sessions', true);
    }

    public function scopeHasNoPaymentMethod(Builder $query): Builder
    {
        return $query->whereDoesntHave('payments', function (Builder $query) {
            $query
                ->whereNull('session_id') // If session ID is not empty then it just a manual fix
                ->where('balance_diff','>', 0);
        });
    }

    public function scopeHasPaymentMethod(Builder $query): Builder
    {
        return $query->whereHas('payments', function (Builder $query) {
            $query
                ->whereNull('session_id') // If session ID is not empty then it just a manual fix
                ->where('balance_diff','>', 0);
        });
    }
}
