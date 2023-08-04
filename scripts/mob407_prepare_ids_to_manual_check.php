<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\ExtractUserIdsFromCsvReport;
use App\SqlOutputToCsv;
use Symfony\Component\Console\Output\ConsoleOutput;

$sqlFileOutput = "/Users/dholovii/code/mysql-dumper/sources/mob407_eu/prev_log_result.txt";
$sqlConverted = "/Users/dholovii/code/mysql-dumper/sources/mob407_eu/prev_log_result.csv";
$consoleOutput = new ConsoleOutput();

$consoleOutput->writeln("Converting SQL output to CSV");
SqlOutputToCsv::parseToFile($sqlFileOutput, $sqlConverted);

$consoleOutput->writeln("Extracting user ids from CSV");
$extractRunner = new ExtractUserIdsFromCsvReport(
    $sqlConverted,
    '/Users/dholovii/code/mysql-dumper/sources/mob407_eu/2_prev_log_result_user_ids.csv'
);

$users = $extractRunner->run();

$consoleOutput->writeln(sprintf("Found %d unique users", $users));


