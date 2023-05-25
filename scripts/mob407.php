<?php

require_once __DIR__ . '/bootstrap.php';

connect();

$analyzer = new \App\Mob407\FixAnalyzer(rootPath: dirname(__DIR__));
$analyzer->run([
//    'force_recreate_users_table',
//    'force_recreate_payments_history_table',
]);
