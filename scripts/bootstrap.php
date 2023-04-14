<?php 

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

function connect() {
    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '33060',
        'database' => 'dumper',
        'username' => 'dumper',
        'password' => 'dumper',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => 'clb_',
    ]);


    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    try {
        $result = $capsule->getConnection()->select("SELECT 1;");

        return count($result) > 0;
    } catch (\Exception $e) {
        return false;
    }
}