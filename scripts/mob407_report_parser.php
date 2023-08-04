<?php

require_once __DIR__ . '/bootstrap.php';

$sourcesDir = dirname(__DIR__) . '/sources/mob407_eu';
$outputDir = dirname(__DIR__) . '/var/mob407_eu/parsed_report';

if (!file_exists($outputDir)) {
    mkdir(directory: $outputDir, recursive: true);
}

$reportParser = new \App\Mob407\ReportParser(
    sourcesDir: $sourcesDir,
    outputDir: $outputDir,
);
$reportParser->parse([
    'mob407_group2.csv',
    'mob407_group3.csv',
    'mob407_fixed.csv',
    'mob407_group1.csv',
]);
