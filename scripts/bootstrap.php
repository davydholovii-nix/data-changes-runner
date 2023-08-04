<?php 

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

enum Env: string {
    case Local = 'local';

    case LocalDms = 'local_dms';

    case LocalCoulomb = 'local_coulomb';

    case QA = 'qa';
}

function connect(Env $env = Env::Local, bool $default = false): bool {
    static $capsule;

    if (is_null($capsule)) {
        $capsule = new Capsule();
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    $credentials = match ($env) {
        Env::Local => local_credentials(),
        Env::LocalDms => local_dms_credentials(),
        Env::LocalCoulomb => local_coulomb(),
        Env::QA => qa_coulomb(),
    };
    $connectionName = $default ? 'default' : $env->value;
    $capsule->addConnection($credentials, $connectionName);

    try {
        $result = $capsule->getConnection($connectionName)->select("SELECT 1;");

        return count($result) > 0;
    } catch (\Exception $e) {
        return false;
    }
}

function qa_coulomb(): array {
    return [
        'driver' => 'mysql',
        'host' => 'cp-qa-fra-nos-jumpbox-1.ev-chargepoint.com',
        'port' => '3306',
        'database' => 'coulomb',
        'username' => 'coulomb',
        'password' => getenv('QA_DB_PASSWORD'),
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => 'clb_',
    ];
}

function local_coulomb(): array {
    return [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '33060',
        'database' => 'dumper',
        'username' => 'dumper',
        'password' => 'dumper',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => 'clb_',
    ];
}

function local_credentials(): array {
    return [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '33060',
        'database' => 'dumper',
        'username' => 'dumper',
        'password' => 'dumper',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => 'clb_',
    ];
}

function local_dms_credentials(): array {
    return [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '33060',
        'database' => 'dumper',
        'username' => 'dumper',
        'password' => 'dumper',
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix' => 'dms_',
    ];
}
