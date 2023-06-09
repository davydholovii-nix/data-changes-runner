<?php

require_once __DIR__ . '/bootstrap.php';

connect();

$time = new \DateTime('now', new \DateTimeZone('EDT'));

$rootDir = dirname(__DIR__);
$sourcesDir = $rootDir . '/sources/mob407_eu';
$reportsDir = $rootDir . '/var/reports/' . $time->format('Md') . '/'. $time->format('H-i-') . bin2hex(random_bytes(2));

if (!file_exists($reportsDir)) {
    mkdir($reportsDir, 0777, true);
}

$analyzer = new \App\Mob407\Mob407AnalyzerV3(
    rootFolder: $rootDir,
    sourcesDir: $sourcesDir,
    reportsFolder: $reportsDir,
);

$options = getopt('ub', ['--force-recreate-users', '--force-recreate-balance-history']);
$analyzer->run([
    \App\Mob407\Mob407AnalyzerV3::OPTION_RECALCULATE_DRIVERS_FLAGS => isset($options['u']) || isset($options['force-recreate-users']),
    \App\Mob407\Mob407AnalyzerV3::OPTION_RECALCULATE_DRIVERS_BALANCE_HISTORY => isset($options['b']) || isset($options['force-recreate-balance-history']),
]);
