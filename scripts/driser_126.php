<?php

require_once __DIR__ . '/bootstrap.php';

use App\Driser126\Migrator;

$options = getopt('f', ['force-recreate::', 'fresh-import::', 'count::', 'export-method::']);
$forceRecreate = isset($options['force-recreate']) || isset($options['f']);
$freshImport = isset($options['fresh-import']);
$count = isset($options['count']) && is_numeric($options['count'])
    ? (int) $options['count']
    : 0;

$migrator = new Migrator(
    rootPath: dirname(__DIR__),
    exportMethod: $options['export-method'] ?? null
);
$migrator
    ->connect()
    ->createTables(forceRecreate: $forceRecreate)
//    ->import(freshImport: $freshImport);
    ->migrate(count: $count);
