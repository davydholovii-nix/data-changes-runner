<?php

require_once __DIR__ . '/bootstrap.php';

use App\Driser126\Migrator;

$options = getopt('f', ['force-recreate::', 'count::']);
$forceRecreate = isset($options['force-recreate']) || isset($options['f']);
$count = isset($options['count']) && is_numeric($options['count'])
    ? intval($options['count'])
    : 100;

$migrator = new Migrator();
$migrator
    ->connect()
    ->createTables(forceRecreate: $forceRecreate)
    ->migrate(count: $count);
