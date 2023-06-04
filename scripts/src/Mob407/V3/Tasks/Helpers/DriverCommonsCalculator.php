<?php

namespace App\Mob407\V3\Tasks\Helpers;

use App\Mob407\Models\PaymentLog;
use Illuminate\Database\Capsule\Manager as DB;
use League\Csv\Reader;

trait DriverCommonsCalculator
{
    private array $organizations = [];

    /** @var null|array List of previously fixed drivers from the group 1 */
    private ?array $previouslyFixedDrivers = null;

    abstract protected function writeExtra(string $key, string $message): void;

    abstract protected function log(string $message, string $level = 'info'): void;

    abstract protected function getSourceFile(string $key): string;
    abstract protected function readAsCommaSeparatedList(string $key): array;

    protected function isPreviouslyFixed(int $driverId): bool
    {
        if (is_null($this->previouslyFixedDrivers)) {
            $this->previouslyFixedDrivers = $this->readAsCommaSeparatedList('previously_fixed_group1');
        }

        return in_array($driverId, $this->previouslyFixedDrivers);
    }

    protected function isAffected(int $driverId): bool
    {
        if (!$this->isPreviouslyFixed($driverId)) { // Check by migration
            $sql = "
                SELECT session_id
                FROM clb_balance_history
                WHERE driver_id = ?
                  AND balance_diff < 0
                  AND is_business = 1
                  AND type = 19
                LIMIT 1
            ";
            return !empty(DB::connection()->select($sql, [$driverId]));
        }

        $dataFixDate = '2023-02-20 16:20:46';
        $sql = "
            SELECT 1 
            FROM clb_user_payment_log
            WHERE user_id = ?
              AND create_date > ?
              AND account_balance < 0
            LIMIT 1
        ";

        return !empty(DB::connection()->select($sql, [$driverId, $dataFixDate]));
    }

    public function getAffectedDate(int $driverId): string
    {
        $sql = "
            SELECT created_at
            FROM clb_balance_history
            WHERE driver_id = ?
            AND balance_diff < 0
            AND is_business = 1
            ORDER BY created_at
            LIMIT 1
        ";

        $result = DB::connection()
            ->select($sql, [$driverId]);

        if (empty($result)) {
            throw new \Exception(sprintf('Affected date for driver %d not found', $driverId));
        }

        return $result[0]->created_at;
    }

    protected function getBalance(int $driverId): ?float
    {
        $sql = "
            SELECT account_balance
            FROM clb_user_payment_log
            WHERE user_id = ?
            AND amount IS NOT NULL
            AND (status = 1 OR type IN (8, 19))
            ORDER BY create_date DESC, id DESC
            LIMIT 1";

        $result = DB::connection()->select($sql, [$driverId]);

        if (!empty($result)) {
            return current($result)->account_balance;
        }

        $this->log(sprintf('Balance for driver %d not found. Default to zero', $driverId), 'warning');

        return null;
    }

    protected function hasBusinessSessions(int $driverId): bool
    {
        return DB::table('drivers_with_business_sessions')
            ->where('driver_id', $driverId)
            ->exists();
    }

    protected function hasPersonalSessions(int $driverId): bool
    {
        $sql = "
            SELECT evc.id, evce.transaction_type, evc.total_amount_to_user
            FROM clb_external_vehicle_charge evc 
            JOIN clb_external_vehicle_charge_ext evce ON evce.evc_id = evc.id 
            WHERE evce.transaction_type = 'PERSONAL'
            AND evc.user_id = ?
            AND evc.total_amount_to_user > 0
            LIMIT 1
        ";

        return !empty(DB::connection()->select($sql, [$driverId]));
    }

    protected function hasIncome(int $driverId): bool
    {
        $affectedDate = $this->getAffectedDate($driverId);

        $sql = "
            SELECT 1
            FROM clb_user_payment_log
            WHERE user_id = ?
            AND create_date >= ?
            AND amount > 0
            AND type IN (1, 4)
            AND status = 1
            LIMIT 1
        ";

        return !empty(DB::connection()->select($sql, [$driverId, $affectedDate]));
    }

    protected function hasRefunds(int $driverId): bool
    {
//        $affectedDate = $this->getAffectedDate($driverId);
        $refund = 2;
        $purchaseCard = 5;
        $promotion = 10;
        $externalSessionRefund = 20;

        return DB::table('user_payment_log')
            ->where('user_id', $driverId)
//            ->where('create_date', '>', $affectedDate)
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
}
