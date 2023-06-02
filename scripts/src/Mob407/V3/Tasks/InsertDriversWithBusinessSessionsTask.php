<?php

namespace App\Mob407\V3\Tasks;

use App\Mob407\V3\Models\Driver;
use League\Csv\Reader;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Database\Capsule\Manager as DB;

class InsertDriversWithBusinessSessionsTask extends AbstractTask
{
    use Traits\HasSources;
    use Traits\DriverCommonsCalculator;
    use Traits\HasExtraOutput;

    public function run(): void
    {
        $query = DB::table('drivers_with_business_sessions')
            ->select('driver_id')
            ->orderBy('driver_id');

        $progress = new ProgressBar($this->getOutput(), $query->clone()->count());
        $progress->start();

        $insert = [];

        foreach ($query->get() as $row) {
            $progress->advance();

            if (Driver::query()->where('id', $row->driver_id)->exists()) {
                continue;
            }

            $insertItem = [];
            $insertItem['id'] = $row->driver_id;
            $insertItem['has_business_sessions'] = true;
            $insertItem['is_affected'] = $this->isAffected($row->driver_id);

            if (!$insertItem['is_affected']) {
                continue;
            }

            $insertItem['has_refunds'] = $this->hasRefunds($row->driver_id);
            $insertItem['balance'] = $insertItem['has_refunds'] && $this->getBalance($row->driver_id);
            $insertItem['has_income'] = $insertItem['has_refunds'] && $this->hasIncome($row->driver_id);
            $insertItem['has_personal_sessions'] = $insertItem['has_refunds'] && $this->hasPersonalSessions($row->driver_id);
            $insertItem['org_code'] = '';

            $insert[] = $insertItem;


            if (count($insert) === 100) {
                DB::table('drivers')->insert($insert);
                $insert = [];
            }
        }

        $this->getOutput()->write("\r", str_repeat(' ', 100) . "\r");
    }
}
