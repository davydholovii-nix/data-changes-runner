<?php

namespace App\Mob407\Actions\V2;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use League\Csv\Reader;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\Output;

class CreateDriverDetails
{
    public static function run(Output $output, string $sourceFile): void
    {
        $output->write(' - Dropping existing table');
        $output->write("\r". str_repeat(" ", 100) . "\r");
        DB::schema()->dropIfExists('driver_details');
        DB::schema()->create('driver_details', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('driver_id');
            $table->integer('org_id')->nullable();
            $table->string('email');
            $table->string('notify_email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('country_name')->nullable();
            $table->string('country_code')->nullable();
            $table->string('lang')->nullable();
        });

        $stream  = fopen($sourceFile, 'r');
        $reader = Reader::createFromStream($stream)->setHeaderOffset(0);

        $output->write("\r". str_repeat(" ", 100) . "\r");
        $output->write(' - Inserting data...');
        $progress = new ProgressBar($output, $reader->count());
        $progress->start();

        foreach ($reader->getRecords() as $driverData) {
            $progress->advance();

            DB::table('driver_details')->insert([
                'driver_id' => $driverData['driver_id'],
                'org_id' => $driverData['org_id'],
                'email' => $driverData['email'],
                'notify_email' => $driverData['notify_email'],
                'first_name' => $driverData['first_name'],
                'middle_name' => $driverData['middle_name'],
                'last_name' => $driverData['last_name'],
                'country_name' => $driverData['country_name'],
                'country_code' => $driverData['country_code'],
                'lang' => $driverData['lang'],
            ]);

        }
        fclose($stream);
        $progress->finish();

        $output->write("\r". str_repeat(" ", 100) . "\r");
        $output->write(' - Done.' . PHP_EOL);
    }
}
