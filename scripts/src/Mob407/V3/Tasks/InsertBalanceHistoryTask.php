<?php

namespace App\Mob407\V3\Tasks;

use App\Mob407\V3\Helpers\HasExtraOutput;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;
use Symfony\Component\Console\Helper\ProgressBar;

class InsertBalanceHistoryTask extends AbstractTask
{
    use HasExtraOutput;

    private ?ProgressBar $progress;
    public function run(): void
    {
        $countAll = DB::table('user_payment_log')
            ->join(
                'drivers_with_business_sessions',
                'drivers_with_business_sessions.driver_id',
                '=',
                'user_payment_log.user_id'
            )
            ->count();

        $this->progress = new ProgressBar($this->getOutput(), $countAll);
        $this->progress->start();

        DB::table('drivers_with_business_sessions')
            ->select('driver_id')
            ->orderBy('driver_id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    if (!DB::table('user_payment_log')->where('user_id', $row->driver_id)->exists()) {
                        $this->log(sprintf('Driver %d has no payment log', $row->driver_id), 'warning');
                        $this->writeExtra('no_payment_drivers', $row->driver_id);
                        continue;
                    }

                    $this->insertDriverBalanceHistory($row->driver_id);
                }
            });

        $this->getOutput()->write("\x0D"); // Move the cursor to the beginning of the line
        $this->getOutput()->write("\x1B[2K"); // Clear the entire line
    }

    private function insertDriverBalanceHistory(int $driverId): void
    {
        $rows = DB::table('user_payment_log')
            ->select([
                'user_payment_log.id',
                'user_payment_log.user_id',
                'user_payment_log.vc_id',
                'user_payment_log.amount',
                'user_payment_log.account_balance',
                'user_payment_log.type',
                'user_payment_log.subtype',
                'user_payment_log.transaction_status',
                'user_payment_log.status',
                'user_payment_log.create_date',
                new Expression("IF(clb_external_vehicle_charge_ext.transaction_type = 'BUSINESS' OR clb_user_payment_log.type = 8, 1, 0) as is_business"),
            ])
            ->leftJoin(
                'external_vehicle_charge_ext',
                'external_vehicle_charge_ext.evc_id',
                '=',
                'user_payment_log.vc_id'
            )
            ->where('user_payment_log.user_id', $driverId)
            ->orderBy('user_payment_log.create_date')
            ->get();

        $insert = [];
        $balanceBefore = null;

        foreach ($rows as $row) {
            $this->progress->advance();
            $insertItem = [];
            $insertItem['driver_id'] = $driverId;
            $insertItem['payment_log_id'] = $row->id;
            $insertItem['session_id'] = $row->vc_id;
            $insertItem['type'] = $row->type;
            $insertItem['amount'] = is_null($row->amount) ? 0.0 : $row->amount;
            $insertItem['balance'] = $row->account_balance;
            $insertItem['balance_before'] = $balanceBefore;
            $insertItem['balance_after'] = $row->account_balance;
            $insertItem['balance_diff'] = $row->amount && !is_null($balanceBefore)
                ? $this->calculateDiff($row, $balanceBefore)
                : 0.0;
            $insertItem['is_business'] = $row->is_business;
            $insertItem['created_at'] = $row->create_date;

            $diff = (int) ($insertItem['balance_diff'] * 100);

            if (is_null($balanceBefore)) {
                $balanceBefore = $row->account_balance;
            } elseif ($diff !== 0) {
                $balanceBefore = $row->account_balance;
            }

            if (is_null($row->vc_id) && $row->type != 8 && $row->is_business) {
                throw new \Exception('Not handled use case');
            }

            if ($row->type == 8 && $diff != 0) {
                $this->writeExtra('personal_charged', $driverId);
            }

            if ($row->type == 5 && $diff != 0) {
                $this->writeExtra('purchase_card', $driverId);
            }

            if (is_null($row->amount)) {
                $this->writeExtra('null_amount', $driverId);
            }

            $insert[] = $insertItem;
        }

        DB::table('balance_history')->insert($insert);
    }

    private function calculateDiff(\stdClass $row, float $balanceBefore): float
    {
        if (!$row->status &&
            $row->type !== 8 && $row->type !== 19 // status can be failed for business sessions who previously had personal payment source
        ) {
            return 0.0;
        }

        if ((int) ($row->amount * 100) === 0) {
            return 0.0;
        }

        return $row->account_balance - $balanceBefore;
    }
}
