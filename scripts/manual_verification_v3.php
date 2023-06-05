<?php

require_once __DIR__ . '/bootstrap.php';

connect();

use Illuminate\Database\Capsule\Manager as DB;

$sourceFile = $argv[1] ?? null;
$rowNum = $argv[2] ?? 1;
$consoleOutput = new \Symfony\Component\Console\Output\ConsoleOutput();

if (is_null($sourceFile)) {
    $consoleOutput->writeln('Source file is not specified');
    exit(1);
}

$sourceStream = fopen($sourceFile, 'r');

if (!$sourceStream) {
    $consoleOutput->writeln('Source file is not readable');
    exit(1);
}

$reader = \League\Csv\Reader::createFromStream($sourceStream);
$reader->setHeaderOffset(0);

$countAll = $reader->count();

while ($rowNum <= $countAll) {
    $row = $reader->fetchOne($rowNum);
    if (empty($row)) {
        break;
    }

    $consoleOutput->writeln('');
    $consoleOutput->writeln('Row #' . $rowNum . '/' . $countAll);
    $consoleOutput->writeln('Driver ID: ' . $row['Driver ID']);
    $consoleOutput->writeln('Amount to refund: ' . $row['Amount to refund']);


    $history = new \Symfony\Component\Console\Helper\Table($consoleOutput);
    $history->setHeaders(['id', 'user_id', 'session_id', 'amount', 'account_balance', 'type', 'status', 's_type', 'create_date']);

    $sql = "
    select
        pl.id,
        pl.user_id,
        evc.id session_id,
        pl.amount,
        -- Sign detected based on payment log type:
        -- Log type 2: Driver refunds (see: Coulomb_report.php:544)
        -- Log types 10, 11: Promotion credits (see: Coulomb_report.php:547)
        -- Log type 20: Roaming refund (see: Coulomb_report.php:562)
        -- Log types 1, 4, 6: Account deposit (see: Coulomb_report.573)
        -- if (pl.type in (2, 10, 11, 20, 1, 4, 6), pl.amount, -1 * pl.amount) amount_with_sign, -- The sign identifies if it's income or outcome
        pl.account_balance,
        pl.`type`,
        pl.status,
        ifnull(evce.transaction_type, if (pl.type = 8, 'BUSINESS', '')) s_type,
        pl.create_date
    from clb_user_payment_log pl
    left join clb_external_vehicle_charge evc on evc.id = pl.vc_id
    left join clb_external_vehicle_charge_ext evce on evce.evc_id = evc.id
    where pl.user_id in (?)
    order by pl.user_id, pl.create_date;
    ";

    $rows = DB::connection()->select($sql, [$row['Driver ID']]);

    $sql = "SELECT session_id FROM clb_balance_history WHERE driver_id = ? AND balance_diff < 0 AND is_business = 1";
    $affectedSessions = DB::connection()->select($sql, [$row['Driver ID']]);
    $affectedSessions = array_map(fn ($row) => $row->session_id, $affectedSessions);

    foreach ($rows as $row) {
        $history->addRow(highlight([
            'log_id' => $row->id,
            'user_id' => $row->user_id,
            'session_id' => $row->session_id,
            'amount' => $row->amount,
            'account_balance' => $row->account_balance,
            'type' => $row->type,
            'status' => $row->status,
            's_type' => $row->s_type,
            'create_date' => $row->create_date,
        ], $affectedSessions));
    }

    $history->render();
    readline('Press enter to continue or Ctrl+C to exit');

    $rowNum++;
}

function highlight(array $row, $affectedSessions): array
{
    if (in_array($row['session_id'], $affectedSessions)) {
        return array_map(
            fn ($value) => '<fg=red>' . $value . '</>',
            $row
        );
    }

    if (in_array($row['type'], [4,1]) && $row['status'] == 0) {
        return array_map(
            fn ($value) => '<fg=magenta>' . $value . '</>',
            $row
        );
    }

    if (in_array($row['type'], [1,4])) {
        return array_map(
            fn ($value) => '<fg=yellow>' . $value . '</>',
            $row
        );
    }

    if (in_array($row['type'], [2,5,10,20])) {
        return array_map(
            fn ($value) => '<fg=green>' . $value . '</>',
            $row
        );
    }

    if (in_array($row['type'], [8, 19])) {
        return $row;
    }

    return array_map(
        fn ($value) => '<fg=blue>' . $value . '</>',
        $row
    );
}
