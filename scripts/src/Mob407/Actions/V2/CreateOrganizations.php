<?php

namespace App\Mob407\Actions\V2;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use League\Csv\Reader;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\Output;

class CreateOrganizations
{
    public static function run(Output $output, string $sourceFile): void
    {
        $output->write(' - Dropping existing table');
        $output->write("\r". str_repeat(" ", 100) . "\r");
        DB::schema()->dropIfExists('organizations');
        DB::schema()->create('organizations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('code');
        });

        $stream  = fopen($sourceFile, 'r');
        $reader = Reader::createFromStream($stream)->setHeaderOffset(0);

        $output->write("\r". str_repeat(" ", 100) . "\r");
        $output->write(' - Inserting data...');
        $progress = new ProgressBar($output, $reader->count());
        $progress->start();

        foreach ($reader->getRecords() as $orgData) {
            $progress->advance();

            DB::table('organizations')->insert([
                'id' => $orgData['id'],
                'name' => $orgData['name'],
                'code' => $orgData['organization_id'],
            ]);
        }
        fclose($stream);
        $progress->finish();

        $output->write("\r". str_repeat(" ", 100) . "\r");
        $output->write(' - Done.' . PHP_EOL);
    }
}
