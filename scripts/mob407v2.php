<?php

require_once __DIR__ . '/bootstrap.php';

connect();

$sourcesDir = dirname(__DIR__) . '/sources/mob407_eu';

$analyzer = new \App\Mob407\FixAnalyzerV2(
    rootPath: dirname(__DIR__),
    sourcesDir: $sourcesDir,
);
$analyzer->run();
