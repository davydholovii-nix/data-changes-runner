<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\SqlOutputToCsv;

$inputFile = $argv[1];
$outputFile = $argv[2];

SqlOutputToCsv::parseToFile($inputFile, $outputFile);
