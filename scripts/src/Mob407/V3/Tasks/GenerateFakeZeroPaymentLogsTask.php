<?php

namespace App\Mob407\V3\Tasks;

use App\Mob407\V3\Helpers\HasSources;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class GenerateFakeZeroPaymentLogsTask extends AbstractTask
{
    use HasSources;

    public function run(): void
    {
        $drivers = $this->getDriverIds();

        $alreadyExistedCount = DB::table('user_payment_log')
            ->where('type', 4)
            ->where('transaction_status', 1)
            ->where('data', 'FAKE')
            ->whereIn('user_id', $drivers)
            ->count();

        if ($alreadyExistedCount == count($drivers)) {
            $this->getOutput()->writeln(' - Fake logs already generated');
            return;
        }

        // Delete previous fake logs
        DB::table('user_payment_log')
            ->where('type', 4)
            ->where('transaction_status', 1)
            ->where('data', 'FAKE')
            ->delete();

        $insertData = Collection::make($drivers)
            ->map(fn ($driverId) => [
                'user_id' => $driverId,
                'vc_id' => null,
                'amount' => 0,
                'account_balance' => 0,
                'type' => 4,
                'transaction_status' => 1,
                'status' => 1,
                'data' => 'FAKE',
                'create_date' => '2022-08-01 00:00:00',
            ])
            ->toArray();

        DB::table('user_payment_log')->insert($insertData);

        $this->getOutput()->writeln(sprintf(' - %d fake logs inserted', count($insertData)));
    }

    private function getDriverIds(): array
    {
        $fileName = $this->getSourceFile('starts_with_negative');
        $commaSeparatedDriverIds = file_get_contents($fileName);
        $driverIds = explode(',', $commaSeparatedDriverIds);
        $driverIds = array_map('intval', $driverIds);

        $existingDrivers = DB::table('drivers_with_business_sessions')
            ->whereIn('driver_id', $driverIds)
            ->get()
            ->pluck('driver_id')
            ->toArray();
        $diff = array_diff($driverIds, $existingDrivers);

        if (!empty($diff)) {
            throw new \RuntimeException(sprintf(
                'Drivers with ids %s do not have business sessions',
                implode(',', $diff)
            ));
        }

        return $driverIds;
    }
}
