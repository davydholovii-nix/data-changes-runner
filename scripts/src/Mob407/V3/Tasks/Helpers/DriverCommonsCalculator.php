<?php

namespace App\Mob407\V3\Tasks\Helpers;

use App\Mob407\Models\PaymentLog;
use Illuminate\Database\Capsule\Manager as DB;
use League\Csv\Reader;

trait DriverCommonsCalculator
{
    private array $organizations = [];

    abstract protected function writeExtra(string $key, string $message): void;

    abstract protected function log(string $message, string $level = 'info'): void;

    protected function isAffected(int $driverId): bool
    {
        return DB::table('balance_history')
            ->where('driver_id', $driverId)
            ->where('balance_diff', '<', 0)
            ->where('is_business', 1)
            ->exists();
    }

    public function getAffectedDate(int $driverId): string
    {
        return DB::table('balance_history')
            ->where('driver_id', $driverId)
            ->where('balance_diff', '<', 0)
            ->where('is_business', 1)
            ->orderBy('created_at', 'asc')
            ->limit(1)
            ->value('created_at');
    }

    protected function getBalance(int $driverId): float
    {
        $balance = DB::table('user_payment_log')
            ->where('user_id', $driverId)
            ->orderBy('create_date', 'desc')
            ->limit(1)
            ->value('account_balance');

        if (!is_null($balance)) {
            return $balance;
        }

        $this->log(sprintf('Balance for driver %d not found. Default to zero', $driverId), 'warning');

        return 0.0;
    }

    protected function hasBusinessSessions(int $driverId): bool
    {
        return DB::table('drivers_with_business_sessions')
            ->where('driver_id', $driverId)
            ->exists();
    }

    protected function hasPersonalSessions(int $driverId): bool
    {
        return DB::table('external_vehicle_charge')
            ->join(
                'external_vehicle_charge_ext',
                'external_vehicle_charge_ext.evc_id',
                '=',
                'external_vehicle_charge.id'
            )
            ->where('external_vehicle_charge_ext.transaction_type', 'BUSINESS')
            ->where('external_vehicle_charge.user_id', $driverId)
            ->exists();
    }

    protected function hasIncome(int $driverId): bool
    {
        $affectedDate = $this->getAffectedDate($driverId);

        return DB::table('user_payment_log')
            ->where('user_id', $driverId)
            ->where('create_date', '>', $affectedDate)
            ->where('amount', '>', 0)
            ->whereIn('type', [1, 4])
            ->where('status', 1)
            ->exists();
    }

    protected function hasRefunds(int $driverId): bool
    {
        $affectedDate = $this->getAffectedDate($driverId);
        $refund = 2;
        $purchaseCard = 5;
        $promotion = 10;
        $externalSessionRefund = 20;

        return DB::table('user_payment_log')
            ->where('user_id', $driverId)
            ->where('create_date', '>', $affectedDate)
            ->where('amount', '>', 0)
            ->whereIn('type', [
                $refund,
                $purchaseCard,
                $promotion,
                $externalSessionRefund,
            ])
            ->where('status', 1)
            ->exists();
    }

    protected function getOrganizationCode(int $organizationId): string
    {
        if (empty($this->organizations)) {
            $this->organizations = $this->readOrganizations($this->getSourceFile('organizations'));
        }

        if (!isset($this->organizations[$organizationId])) {
            $this->log('Organization [' . $organizationId . '] not found', 'warning');

            return '';
        }

        return $this->organizations[$organizationId];
    }

    private function readOrganizations(string $organizationsFile): array
    {
        $csv = fopen($organizationsFile, 'r');
        $reader = Reader::createFromStream($csv)->setHeaderOffset(0);

        $result = [];

        foreach ($reader->getRecords() as $organization) {
            $result[$organization['id']] = $organization['organization_id'];
        }

        fclose($csv);

        return $result;
    }

    private function equal(float $a, float $b): bool
    {
        return $a * 100 == $b * 100;
    }
}
