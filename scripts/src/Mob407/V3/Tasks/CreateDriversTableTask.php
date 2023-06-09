<?php

namespace App\Mob407\V3\Tasks;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

class CreateDriversTableTask extends AbstractTask
{
    public function run(): void
    {
        $this->dropTable();
        $this->log('drivers table dropped');

        $this->createTable();
        $this->log('drivers table created');
    }

    private function dropTable(): void
    {
        DB::schema()->dropIfExists('drivers');
    }

    private function createTable(): void
    {
        DB::schema()->create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('email')->nullable();
            $table->string('notify_email')->nullable();
            $table->string('country_name')->nullable();
            $table->string('country_code')->nullable();
            $table->string('pref_lang')->nullable();
            $table->string('org_code')->nullable();
            $table->float('balance')->nullable();
            $table->boolean('is_affected')->default(false);
            $table->boolean('has_business_sessions')->default(false);
            $table->boolean('has_personal_sessions')->default(false);
            $table->boolean('has_income')->default(false);
            $table->boolean('has_refunds')->default(false);
        });
    }
}
