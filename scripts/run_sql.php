<?php

require_once __DIR__ . '/bootstrap.php';
connect();

use Illuminate\Database\Capsule\Manager as DB;

$sql = "
select distinct pl.id as driver_id
from clb_user_payment_log pl
join clb_external_vehicle_charge evc on pl.vc_id = evc.id
join clb_external_vehicle_charge_ext evce on evce.evc_id = evc.id
where 1
    and pl.account_balance < 0               -- Negative user balance
    and evce.transaction_type = 'BUSINESS'   -- After a business session
    and not exists(                          -- Make sure it's a very first driver's action
        select 1
        from clb_user_payment_log pl2
        where 1
            and pl2.user_id = pl.user_id
            and pl2.create_date < pl.create_date
    )
";

$result = DB::connection()->select($sql);

echo \Illuminate\Support\Collection::make($result)->pluck('driver_id')->implode(',');
