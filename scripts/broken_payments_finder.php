<?php

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as DB;

connect();

$consoleOutput = new \Symfony\Component\Console\Output\ConsoleOutput();

$consoleOutput->writeln('Start searching users with broken payments...');

$progress = new \Symfony\Component\Console\Helper\ProgressBar($consoleOutput);
$progress->setMaxSteps(
    DB::table('users')
        ->select('id')
        ->where('has_business_sessions', '1')
        ->count()
);


$outputFile = __DIR__ . '/broken_payments.txt';
$outputStream = fopen($outputFile, 'w');
$output = new \Symfony\Component\Console\Output\StreamOutput($outputStream);

$progress->start();

$lineCount = 0;

DB::table('users')
    ->select('id')
    ->where('has_business_sessions', '1')
    ->orderBy('id')
    ->get()
    ->each(function ($row) use ($progress, $output, &$lineCount) {
        $progress->advance();
        $userId = $row->id;

        if (DB::table('user_payment_log')->where('user_id', $userId)->count() === 0) {
            return;
        }

        $payments = DB::table('user_payment_log')
            ->select(['id', 'user_id', 'amount', 'account_balance', 'type', 'subtype', 'transaction_status'])
            ->where('user_id', $userId)
            ->where('create_date', '>', '2022-08-25')
            ->orderBy('create_date')
            ->get();

        $prevBalance = null;

        foreach ($payments as $payment) {
            $paymentType = intval($payment->type);
            $paymentAmount = floatval($payment->amount);
            $paymentBalance = floatval($payment->account_balance);

            if ($prevBalance === null) {
                $prevBalance = $paymentBalance;
                continue;
            }

            if ($paymentType !== 1 || $paymentAmount === 0.0) {
                $prevBalance = $paymentBalance;
                continue;
            }

            $balanceA = $paymentBalance - $paymentAmount;
            $balanceB = $prevBalance;

            if ($balanceA === $balanceB) {
                continue;
            }

            if ($balanceA !== $balanceB) {
                if ($balanceB === 0.0 && $balanceA !== 0.0) {
                    if($lineCount++ === 10) {
                        $lineCount = 0;
                        $output->writeln('');
                    }

                    $output->write($payment->user_id . ',');
                    return;
                }

                if (abs(($balanceA-$balanceB)/$balanceB) >= 0.01) {
                    if($lineCount++ === 10) {
                        $lineCount = 0;
                        $output->writeln('');
                    }

                    $output->write($payment->user_id . ',');
                    return;
                }
            }

            $prevBalance = $paymentBalance;
        }
    });

$progress->finish();

$consoleOutput->write("\r" . str_repeat(' ', 100) . "\r");
$consoleOutput->writeln('See results in file: ' . $outputFile);

fclose($outputStream);

