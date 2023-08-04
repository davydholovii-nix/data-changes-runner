## MOB-407

... 

## DRISER-126 DMS Home installations migration job

### How to run

Common steps:

1. Paste backup files to the `sources/driser126` folder
2. Run local DB with `docker-compose up`
3. Open `scripts` folder in terminal
4. Install dependencies `composer install`

Run migrate script from PhpStorm:

To be added...

Run from terminal:

1. Start DMS home installations locally
2. `php driser_126.php --force-recreate --export-method=api --count=10`
