<?php

require_once __DIR__ . '/bootstrap.php';

connect();

use App\Mob407\Actions\PrepareDriversTable;


$driver = \App\Mob407\Models\Driver::find(34975395);

$hasPersonalSession = PrepareDriversTable::hasPersonalRoamingSessions($driver);

var_dump($hasPersonalSession);

