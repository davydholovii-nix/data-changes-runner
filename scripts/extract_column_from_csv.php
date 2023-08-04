<?php

$filename = $argv[1];
$columnName = $argv[2];
$limit = $argv[3] ?? null;

if (!file_exists($filename)) {
    echo "File $filename does not exist\n";
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

$csv = \League\Csv\Reader::createFromPath($filename, 'r')->setHeaderOffset(0);

if ($csv->getHeader()[$columnName] ?? null) {
    echo "Column $columnName does not exist\n";
    exit(1);
}

foreach ($csv->getRecords() as $record) {
    echo $record[$columnName] . ",";
    if ($limit && --$limit === 0) {
        break;
    }
}
