<?php

namespace App\Driser126\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $request_id
 * @property int $external_id
 * @property string $employer_id
 * @property string $installer_id
 * @property string $installer_name
 * @property string $mac_address
 * @property string $job_status
 * @property string $job_document
 * @property bool $synced_to_nos
 * @property string $currency_iso_code
 * @property float $amount
 * @property float $subtotal_amount
 * @property float $vat_rate
 * @property float $vat_amount
 * @property float $actual_amount
 * @property float $approved_amount
 * @property string $installation_date
 * @property string $activation_date
 * @property string $completion_date
 * @property string $created_at
 * @property string $updated_at
 *
 * @property-read HomeChargerRequest $request
 */
class InstallerJob extends Model
{
    protected $table = 'installer_jobs';

    protected $connection = \Env::LocalDms->value;

    protected $casts = [
        'synced_to_nos' => 'bool',
    ];

    public static function findForDriverAndConnection(int $driverId, int $connectionId): ?InstallerJob
    {
        /** @var InstallerJob|null $installerJob */
        $installerJob = static::query()
            ->whereHas('request', fn ($query) => $query->where('driver_id', $driverId)->where('connection_id', $connectionId))
            ->first();

        return $installerJob;
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(HomeChargerRequest::class, 'request_id', 'id');
    }
}
