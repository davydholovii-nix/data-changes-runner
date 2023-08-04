<?php

namespace App\Driser126\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read int $id
 * @property int $user_id
 * @property string|null $leaseco_org_name
 * @property string|null $connection_name
 * @property string|null $employer_id
 * @property string|null $driver_name
 * @property int|null $transaction_type  // 1 = public session, 2=home session, 3=home installation, 4=monthly driver fee
 * @property int|null $installation_status  // Deprecated; 0 = Unset, 1 = Not Scheduled, 2 = Scheduled, 3 = Completed, 4 = Higher Cost, 5 = Uninstallable, 6 = Unresponsive, 7 = Other
 * @property string|null $installation_date
 * @property string|null $installer_id
 * @property string|null $installer_name
 * @property string|null $install_location
 * @property string|null $installation_company
 * @property string|null $home_serial_num
 * @property int|null $reimbursement_id  // unsigned
 * @property string|null $currency_iso_code
 * @property float|null $amount
 * @property float|null $subtotal_amount  // default 0.000
 * @property float|null $vat_rate  // default 0.00
 * @property float|null $vat_amount  // default 0.000
 * @property float|null $actual_amount  // unsigned
 * @property float|null $approved_amount  // unsigned
 * @property string|null $create_date  // default CURRENT_TIMESTAMP
 * @property string|null $update_date  // default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP
 * @property int|null $job_status  // 0 = Unset, 1 = Open, 2 = In Progress, 3 = Completed, 4 = Not Installed (deprecated), 5 = Install Scheduled, 6 = Site Survey Pending, 7 = Survey OK-Not Scheduled, 8 = Closed-Not Installed-Higher Cost, 9 = Closed-Not Installed-Uninstallable, 10 = Closed-Not Installed-Unresponsive, 11 = Closed-Not Installed-Other, 12 = Charger activation in process, 13 = Charger activated, 14 = Charger activation failed, 15 = Job on-hold by driver
 * @property string|null $job_document
 * @property int|null $external_id  // For home installation jobs, stores the ID of the record in the installer portal
 * @property int $connection_id
 * @property string|null $completion_date  // date when job is marked completed
 * @property int $driver_group_id
 * @property string|null $activation_date
 *
 * @property-read UserLogin $userLogin
 * @property-read BusinessDetails $businessDetails
 */
class LeaseCoTransaction extends Model
{
    protected $table = 'leaseco_transaction';

    protected $connection = \Env::LocalCoulomb->value;

    public function userLogin(): BelongsTo
    {
        return $this->belongsTo(UserLogin::class, 'user_id', 'user_id');
    }

    public function businessDetails(): HasOne
    {
        return $this->hasOne(BusinessDetails::class, 'subscriber_id', 'user_id')
            ->where('connection_id', '=', 'connection_id');
    }
}
