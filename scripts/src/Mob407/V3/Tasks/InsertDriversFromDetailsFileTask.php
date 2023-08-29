<?php

namespace App\Mob407\V3\Tasks;

use App\Mob407\V3\Helpers\HasExtraOutput;
use App\Mob407\V3\Helpers\HasSources;
use App\Mob407\V3\Helpers\Progress;
use App\Mob407\V3\Tasks\Helpers\DriverCommonsCalculator;
use App\Mob407\V3\Tasks\Helpers\JsonReader;
use League\Csv\Reader;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Database\Capsule\Manager as DB;

class InsertDriversFromDetailsFileTask extends AbstractTask
{
    use HasSources;
    use HasExtraOutput;
    use DriverCommonsCalculator;
    use JsonReader;

    public function run(): void
    {
        $filename = $this->getSourceFile('driver_details');

        $progress = Progress::init($this->getOutput(), $this->countLines($filename));

        foreach ($this->getJsonFileReader($filename) as $driver) {
            if ($driver === null) {
                continue;
            }

            $progress->advance();

            $insertItem = $this->insertDataFormDetails($driver);
            $insertItem['has_business_sessions'] = $this->hasBusinessSessions($driver['driver_id']);
            if (!$insertItem['has_business_sessions']) {
                $this->writeExtra('has_no_business_sessions', $driver['driver_id'] . ',');
                continue;
            }
            $insertItem['previously_fixed_at'] = $this->getPreviouslyFixedAt($driver['driver_id']);
            $insertItem['is_affected'] = $this->isAffected($driver['driver_id'], $insertItem['previously_fixed_at']);
            if (!$insertItem['is_affected']) {
                $this->writeExtra('is_not_affected', $driver['driver_id'] . ',');
                continue;
            }
            $insertItem['balance'] = $this->getBalance($driver['driver_id']);
            $insertItem['org_code'] = $this->getOrganizationCode($driver['org_id'] ?? 0) ?: '';
            $insertItem['has_refunds'] = $insertItem['is_affected'] && $this->hasRefunds($driver['driver_id']);
            $insertItem['has_income'] = (!$insertItem['has_refunds']) && $this->hasIncome($driver['driver_id']);
            $insertItem['has_personal_sessions'] = (!$insertItem['has_refunds']) && $this->hasPersonalSessions($driver['driver_id']);

            DB::table('drivers')->insert($insertItem);
        }

        $progress->finish(clean: true);
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
