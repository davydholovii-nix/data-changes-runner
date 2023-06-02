<?php

namespace App\Mob407\V3\Tasks;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

class CreateDriversWithBusinessSessionsTableTask extends AbstractTask
{
    public function run(): void
    {
        if (DB::schema()->hasTable('drivers_with_business_sessions')) {
            $this->log('drivers_with_business_sessions table already exists');
            return;
        }

        $this->createTable();
        $this->log('drivers_with_business_sessions table created');

        $this->insertData();
    }

    private function createTable(): void
    {
        DB::schema()->create('drivers_with_business_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('driver_id')->index();
            $table->integer('count_sessions');
        });
    }

    private function insertData()
    {
        DB::connection()
            ->select("
                INSERT INTO clb_drivers_with_business_sessions (driver_id, count_sessions)
                SELECT 
                    clb_external_vehicle_charge.user_id as driver_id,
                    count(clb_external_vehicle_charge.user_id) as count_sessions
                FROM clb_external_vehicle_charge
                INNER JOIN clb_external_vehicle_charge_ext ON clb_external_vehicle_charge_ext.evc_id = clb_external_vehicle_charge.id
                WHERE clb_external_vehicle_charge_ext.transaction_type = 'BUSINESS' 
                  AND clb_external_vehicle_charge.total_amount_to_user > 0
                GROUP BY clb_external_vehicle_charge.user_id
            ");
    }
}
