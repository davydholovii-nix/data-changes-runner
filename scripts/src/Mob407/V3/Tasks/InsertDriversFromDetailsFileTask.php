<?php

namespace App\Mob407\V3\Tasks;

use App\Mob407\V3\Helpers\HasExtraOutput;
use App\Mob407\V3\Helpers\HasSources;
use App\Mob407\V3\Tasks\Helpers\DriverCommonsCalculator;
use League\Csv\Reader;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Database\Capsule\Manager as DB;

class InsertDriversFromDetailsFileTask extends AbstractTask
{
    use HasSources;
    use HasExtraOutput;
    use DriverCommonsCalculator;

    public function run(): void
    {
        $csv = fopen($this->getSourceFile('driver_details'), 'r');
        $reader = Reader::createFromStream($csv)->setHeaderOffset(0);

        $progress = new ProgressBar($this->getOutput(), $reader->count());
        $progress->start();

        foreach ($reader->getRecords() as $driver) {
            $progress->advance();

            $insertItem = $this->insertDataFormDetails($driver);
            $insertItem['has_business_sessions'] = $this->hasBusinessSessions($driver['driver_id']);
            if (!$insertItem['has_business_sessions']) {
                throw new \Exception(sprintf('The driver %d from driver details has no business sessions', $driver['driver_id']));
            }
            $insertItem['is_affected'] = $insertItem['has_business_sessions'] && $this->isAffected($driver['driver_id']);
            if (!$insertItem['is_affected']) {
                throw new \Exception(sprintf('The driver %d from driver details file is not affected', $driver['driver_id']));
            }
            $insertItem['balance'] = $this->getBalance($driver['driver_id']);
            $insertItem['org_code'] = $this->getOrganizationCode($driver['org_id'] ?? 0) ?: '';
            $insertItem['has_refunds'] = $insertItem['is_affected'] && $this->hasRefunds($driver['driver_id']);
            $insertItem['has_income'] = (!$insertItem['has_refunds']) && $this->hasIncome($driver['driver_id']);
            $insertItem['has_personal_sessions'] = (!$insertItem['has_refunds']) && $this->hasPersonalSessions($driver['driver_id']);

            DB::table('drivers')->insert($insertItem);
        }

        fclose($csv);
        $this->getOutput()->write("\x0D"); // Move the cursor to the beginning of the line
        $this->getOutput()->write("\x1B[2K"); // Clear the entire line
    }

    private function insertDataFormDetails(array $details): array
    {
        return [
            'id' => $details['driver_id'],
            'first_name' => $details['first_name'],
            'last_name' => $details['last_name'],
            'middle_name' => $details['middle_name'],
            'email' => $details['email'],
            'notify_email' => $details['notify_email'],
            'country_name' => $details['country_name'],
            'country_code' => $details['country_code'],
            'pref_lang' => $details['lang'],
        ];
    }
}
