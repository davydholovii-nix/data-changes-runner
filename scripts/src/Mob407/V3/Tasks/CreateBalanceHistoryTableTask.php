<?php

namespace App\Mob407\V3\Tasks;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

class CreateBalanceHistoryTableTask extends AbstractTask
{
    public function run(): void
    {
        $this->dropTable();
        $this->log('balance_history table dropped');

        $this->createTable();
        $this->log('balance_history table created');
    }

    private function dropTable(): void
    {
        DB::schema()->dropIfExists('balance_history');
    }

    private function createTable(): void
    {
        DB::schema()->create('balance_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('driver_id')->index();
            $table->unsignedInteger('payment_log_id');
            $table->float('amount', 8, 2);
            $table->float('balance', 8, 2);
            $table->float('balance_before', 8, 2)->nullable();
            $table->float('balance_after', 8, 2);
            $table->float('balance_diff', 8, 2);
            $table->boolean('is_business')->default(false);
            $table->timestamp('created_at');
        });
    }
}
