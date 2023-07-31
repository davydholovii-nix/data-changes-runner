<?php

namespace App\Driser126\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $subscriber_id
 * @property-read int $connection_id
 * @property-read int $driver_group_id
 * @property-read int $company_id
 * @property-read string $status
 * @property-read string $address1
 * @property-read string $address2
 * @property-read string $city
 * @property-read string $state_code
 * @property-read string $state
 * @property-read int $country_id
 * @property-read string $country_code
 * @property-read string $zipcode
 * @property-read string $contact_number
 * @property-read string $contact_number_dialing_code
 * @property-read string $connection_approval_date
 * @property-read string $connection_discontinue_date
 * @property-read string $create_date
 * @property-read string $update_date
 *
 * @property-read Company $company
 */
class BusinessDetails extends Model
{
    protected $table = 'business_details';

    protected $connection = \Env::QA->value;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }
}
