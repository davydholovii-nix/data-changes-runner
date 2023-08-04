<?php

namespace App\Driser126;

use App\Driser126\Models\BusinessDetails;
use App\Driser126\Models\HomeChargerRequest;
use App\Driser126\Models\InstallerJob;
use App\Driser126\Models\LeaseCoTransaction;
use Env;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Migrator
{
    private const DEFAULT_CHUNK_SIZE = 100;

    private const EXPORT_OPTIONS = [
        ExportMethod::JSON->name => [],
        ExportMethod::DB->name => ['connection' => Env::LocalCoulomb->value],
        ExportMethod::API->name => ['base_uri' => 'http://localhost:8080'],
    ];

    private ExportMethod $exportMethod;

    /**
     * Country names mapped by country id
     *
     * @var array <int,string>
     */
    private array $countryNames = [];

    /**
     * State IDs mapped by country ID and state code
     *
     * @var array <int,array<string,string>>
     */
    private array $stateIds = [];

    /**
     * Company details mapped by connection ID
     *
     * @var array<int,array{name:string,org_id:string}>
     */
    private array $companies = [];

    /**
     * Connection names mapped by connection id
     *
     * @var array<int,string>
     */
    private array $connectionNames = [];

    private ?array $exportedRequests = null;

    public function __construct(
        private readonly string $rootPath,
        ?string $exportMethod = null
    ) {
        $this->exportMethod = ExportMethod::JSON;

        if (empty($exportMethod)) {
            return;
        }

        foreach (ExportMethod::cases() as $allowedMethod) {
            if (strtolower($allowedMethod->name) !== strtolower($exportMethod)) {
                continue;
            }

            $this->exportMethod = $allowedMethod;

            break;
        }
    }

    public function migrate(int $count): void
    {
        $this->logMessage('Migrating data NOS home installation jobs to DMS Home installations service');

        $this->createHomeChargerRequests($count);
        $this->createInstallerJobs();
    }

    private function createHomeChargerRequests(int $count): int
    {
        $created = 0;
        $processed = 0;
        $countBusinessDetails = $count ?: $this->countBusinessDetails();
        $this->logMessage(sprintf('Processing %d row(s) from clb_business_details table', $countBusinessDetails));

        foreach ($this->getBusinessDetails() as $businessDetails) {
            $requestId = $this->convertBusinessDetailsToHomeChargerRequest($businessDetails);
            $processed++;

            // Log progress after every 50 rows
            $this->logMessage(sprintf(
                'Processed %d/%d clb_business_details row(s)',
                $processed, $countBusinessDetails
            ));

            if ($count === 0) {
                continue;
            }

            $this->exportedRequests[$requestId] = [
                'driverId' => $businessDetails->subscriber_id,
                'connectionId' => $businessDetails->connection_id
            ];


            if ($processed >= $count) {
                break;
            }
        }

        $this->logMessage(sprintf(
            'Created %d home charger request(s)',
            $created
        ));

        return $processed;
    }

    private function createInstallerJobs(): int
    {
        $processed = 0;
        $countLeaseCoTransactions = count($this->exportedRequests) ?: $this->countLeaseCoTransactions();

        $this->logMessage(sprintf('Processing %d row(s) from clb_leaseco_transactions table', $countLeaseCoTransactions));

        foreach ($this->getLeaseCoTransactions() as $leaseCoTransaction) {
            $processed++;
            /** @var HomeChargerRequest|null $request */
            $request = HomeChargerRequest::query()
                ->where('driver_id', $leaseCoTransaction->user_id)
                ->where('connection_id', $leaseCoTransaction->connection_id)
                ->first();

            if (empty($request)) {
                $this->logMessage(sprintf(
                    'Home charger request for driver_id %d and connection_id %d not found',
                    $leaseCoTransaction->user_id,
                    $leaseCoTransaction->connection_id
                ), 'warn');

                continue;
            }

            $installerJob = $this->convertLeaseCoTransactionToInstallerJob($leaseCoTransaction, $request);

            $this->exportIJ($installerJob);

            if ($processed % 50 === 0) {
                $this->logMessage(sprintf(
                    'Processed %d/%d clb_leaseco_transactions row(s)',
                    $processed, $countLeaseCoTransactions
                ));
            }
        }

        $this->logMessage(sprintf(
            'Created %d installer job(s)',
            $processed
        ));

        return $processed;
    }

    private function convertBusinessDetailsToHomeChargerRequest(BusinessDetails $businessDetails): int
    {
        $numRequestsCreated = 0;

        $request = HomeChargerRequest::query()
            ->where('driver_id', $businessDetails->subscriber_id)
            ->where('connection_id', $businessDetails->connection_id)
            ->first();
        $transaction = $this->findLeaseCoTransaction($businessDetails->subscriber_id, $businessDetails->connection_id);

        if ($request === null) {
            $numRequestsCreated++;
            $request = $this->createPendingHomeChargerRequest($businessDetails, $transaction);

            $this->exportHCR($request);
        }

        if ($businessDetails->connection_approval_date) {
            // Another home charger request
            $request->updated_at = $businessDetails->connection_approval_date;
            $request->request_status = 'APPROVED';
            $this->exportHCR($request);
        }

        if (empty($businessDetails->connection_discontinue_date)) {
            return $numRequestsCreated;
        }

        $request->updated_at = $businessDetails->connection_discontinue_date;
        // if request has never got approved then it had been denied right away
        $request->request_status = $businessDetails->connection_approval_date ? 'DELETED' : 'DENIED';
        $this->exportHCR($request);


        return $request->id ?: microtime(as_float: true) * 1000000;
    }

    private function createPendingHomeChargerRequest(
        BusinessDetails $businessDetails,
        ?LeaseCoTransaction $transaction = null
    ): HomeChargerRequest {
        $request = new HomeChargerRequest();
        $request->connection_id = $businessDetails->connection_id;
        $request->driver_id = $businessDetails->subscriber_id;
        $request->driver_group_id = $businessDetails->driver_group_id;
        $request->company_id = $businessDetails->company_id;
        $request->phone_number = $businessDetails->contact_number;
        $request->dialing_code = $businessDetails->contact_number_dialing_code;
        $request->address1 = $businessDetails->address1;
        $request->address2 = $businessDetails->address2;
        $request->zip_code = $businessDetails->zipcode;
        $request->city = $businessDetails->city;
        $request->country_id = $businessDetails->country_id;
        $request->country_code = $businessDetails->country_code;
        $request->country_name = $this->getCountryName($businessDetails->country_id);
        $request->state_id = $businessDetails->state_code
            ? $this->getStateIdByCountryIdAndStateCode($businessDetails->country_id, $businessDetails->state_code)
            : null;
        $request->state_code = $businessDetails->state_code;
        $request->state_name = $businessDetails->state;
        $request->created_at = $businessDetails->create_date;
        $request->updated_at = $businessDetails->create_date;
        $request->request_status = 'REQUESTED';


        $driverName = explode(' ', $transaction->driver_name ?? $this->getDriverName($businessDetails->subscriber_id));

        $request->driver_first_name = array_shift($driverName);
        $request->driver_last_name = implode(" ", $driverName);

        // Fields that have to be filled based on clb_leaseco_transactions table or others
        $request->leaseco_org_name = $this->getCompanyNameByConnectionId($businessDetails->connection_id);
        $request->currency_iso_code = $this->getDriverCurrency($businessDetails->subscriber_id);
        $request->connection_name = $this->getConnectionName($businessDetails->connection_id);
        $request->email = $this->getDriverEmail($businessDetails->subscriber_id);
        $request->leaseco_org_id = $this->getLeaseCoOrgIdByConnectionId($businessDetails->connection_id);
        $request->driver_verf_values = $this->getDriverVerificationValues($businessDetails->subscriber_id, $businessDetails->connection_id);

        return $request;
    }

    private function convertLeaseCoTransactionToInstallerJob(
        LeaseCoTransaction $transaction,
        HomeChargerRequest $request
    ): InstallerJob {
        // TODO: Should we keep multiple installer jobs as we have in legacy system?
        // TODO: The current behavior is to keep only one installer job per driver and connection
        $installerJob = InstallerJob::findForDriverAndConnection($transaction->user_id, $transaction->connection_id);

        if ($installerJob === null) {
            $installerJob = new InstallerJob();
            $installerJob->id = $transaction->id;
        }

        $installerJob->request_id = $request->id;
        $installerJob->external_id = $transaction->external_id;
        $installerJob->employer_id = $transaction->employer_id;
        $installerJob->installer_id = $transaction->installer_id;
        $installerJob->installer_name = $transaction->installer_name;
        $installerJob->currency_iso_code = $transaction->currency_iso_code;
        $installerJob->amount = $transaction->amount;
        $installerJob->subtotal_amount = $transaction->subtotal_amount;
        $installerJob->vat_amount = $transaction->vat_amount;
        $installerJob->vat_rate = $transaction->vat_rate;
        $installerJob->approved_amount = $transaction->approved_amount;
        $installerJob->actual_amount = $transaction->actual_amount;
        $installerJob->mac_address = $transaction->home_serial_num;
        $installerJob->job_document = $transaction->job_document;
        $installerJob->job_status = $transaction->job_status;
        $installerJob->activation_date = $transaction->activation_date;
        $installerJob->completion_date = $transaction->completion_date;
        $installerJob->created_at = $transaction->create_date;
        $installerJob->updated_at = $transaction->update_date;
        $installerJob->synced_to_nos = true;

        return $installerJob;
    }

    // -----------------------------------------------------------------------------
    // ----------------------- Utility methods abstractions ------------------------
    // -----------------------------------------------------------------------------

    private function logMessage(string $message, string $level = 'info'): void
    {
        static $output;

        if (is_null($output)) {
            $output = new ConsoleOutput();
        }

        $output->writeln(sprintf("[%s] %s %s", date('Y-m-d H:i:s'), strtoupper($level), $message));
    }

    /**
     * Wraps callable into an iterator to fetch data in chunks
     *
     * @param callable $fetcher Method that loads data from database. The method is called until reaches limit or returns empty array
     * @param int $limit (Optional) Limit of rows to load
     * @return \Iterator
     */
    private function createFetcherIterator(callable $fetcher, int $limit = 0): \Iterator
    {
        return new class ($fetcher, $limit) implements \Iterator {
            private int $rowsFetched = 0;
            private int $offset = 0;
            private array $loaded = [];
            private $current = null;

            public function __construct(
                private readonly \Closure $fetcher,
                private readonly int $limit,
            ) {}
            public function next(): void {
                $this->current = null;

                if ($this->limit > 0 && $this->rowsFetched >= $this->limit) {
                    return;
                }

                if (empty($this->loaded)) {
                    $this->loaded = call_user_func($this->fetcher, $this->offset);
                    $this->offset += count($this->loaded);

                    if (empty($this->loaded)) {
                        return;
                    }
                }

                $this->rowsFetched++;
                $this->current = array_shift($this->loaded);
            }

            public function rewind(): void
            {
                $this->rowsFetched = 0;
                $this->offset = 0;
                $this->loaded = [];
            }

            public function current(): mixed
            {
                return $this->current;
            }

            public function key(): mixed
            {
                return $this->rowsFetched;
            }

            public function valid(): bool
            {
                if (is_null($this->current)) {
                    $this->next();
                }

                return !empty($this->current);
            }
        };
    }

    private function exportHCR(HomeChargerRequest $request): bool
    {
        static $exporter;

        if (empty($exporter)) {
            $exporter = $this->createHCRExporter();
        }

        return $exporter($request);
    }

    private function exportIJ(InstallerJob $installerJob): bool
    {
        static $exporter;

        if (empty($exporter)) {
            $exporter = $this->createIJExporter();
        }

        return $exporter($installerJob);
    }

    private function createHCRExporter(): \Closure
    {
        return match ($this->exportMethod) {
            ExportMethod::JSON => function (HomeChargerRequest $r) {
                $this->logMessage($r->toJson());
                return true;
            },
            // DB exporter just saves request to local database
            ExportMethod::DB => function (HomeChargerRequest $r) {
                $r->setConnection(self::EXPORT_OPTIONS[ExportMethod::DB->name])->save();
                $this->logMessage(sprintf("Home charger request %s exported successfully", $r->id));
                return true;
            },
            // API exporter exports data to local DMS home installations
            ExportMethod::API => function (HomeChargerRequest $r) {

                $result = $this->callApiEndpoint(
                    uri: '/v1/data-migration/home-charger-requests',
                    body: $r->toJson(),
                );

                if ($result['success']) {
                    // The job export relies on request existence so saving it to DB as well
                    if (!empty($result['data']['requestId']) && $r->id !== (int) $result['data']['requestId']) {
                        $r->id = $result['data']['requestId'];
                    }
                    $r->save();
                    $this->logMessage(sprintf("Home charger request %s exported successfully", $r->id));
                    return true;
                }

                $this->logMessage(sprintf(
                    "Home charger request for driver %d and connection %d export failed: %s",
                    $r->driver_id, $r->connection_id, $result['data']['error'] ?? ''
                ), 'error');

                return false;
            }
        };
    }

    private function createIJExporter(): \Closure
    {
        return match ($this->exportMethod) {
            ExportMethod::JSON => function (InstallerJob $ij) {
                $this->logMessage($ij->toJson());

                return true;
            },
            ExportMethod::DB => function (InstallerJob $ij) {
                $ij->setConnection(self::EXPORT_OPTIONS[ExportMethod::DB->name])->save();
                $this->logMessage(sprintf("Installer job %s exported successfully", $ij->id));

                return true;
            },
            ExportMethod::API => function (InstallerJob $ij) {
                $result = $this->callApiEndpoint(
                    uri: '/v1/data-migration/installer-jobs',
                    body: $ij->toJson(),
                );

                if ($result['success']) {
                    $this->logMessage(sprintf("Installer job for request %d has been exported", $ij->request_id));

                    return true;
                }

                $this->logMessage(sprintf(
                    'Cannot export installer job for request %d. Response: %s',
                    $ij->request_id, json_encode($result['data'] ?: null)
                ));

                return false;
            },
        };
    }

    /**
     * @param string $uri
     * @param string $body
     * @return array{success: bool, data: array}
     */
    private function callApiEndpoint(string $uri, string $body): array
    {
        static $client;

        if (empty($client)) {
            $client = new Client(self::EXPORT_OPTIONS[ExportMethod::API->name]);
        }

        $request = new Request(
            method: 'POST',
            uri: $uri,
            headers: ['Content-Type' => 'application/json'],
            body: $body
        );

        try {
            $response = $client->sendRequest($request);
            $data = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                return ['success' => true, 'data' => $data];
            }

            return ['success' => false, 'data' => $data];
        } catch (ClientExceptionInterface $e) {
            return ['success' => false, 'data' => ['error' => $e->getMessage()]];
        }
    }

    // -----------------------------------------------------------------------------
    // ----------------------- Read/Write database methods -------------------------
    // -----------------------------------------------------------------------------

    /**
     * Returns business details within an iterator
     * From iterator perspective it contains all the business details, but they are not loaded
     * in memory all at once but slice by slice
     *
     * @return \Iterator|BusinessDetails[]
     */
    private function getBusinessDetails(): \Iterator
    {
        return $this->createFetcherIterator($this->loadBusinessDetails(...));
    }

    /**
     * Returns leaseco transactions within an iterator
     * From iterator perspective it contains all the business details, but they are not loaded
     * in memory all at once but slice by slice
     *
     * @return \Iterator|LeaseCoTransaction[]
     */
    private function getLeaseCoTransactions(): \Iterator
    {
        return $this->createFetcherIterator(
            $this->loadLeaseCoTransactions(...), // Function that loads transactions from database
        );
    }

    private function findLeaseCoTransaction(int $driverId, int $connectionId): ?LeaseCoTransaction
    {
        /** @var LeaseCoTransaction|null $transaction */
        $transaction = LeaseCoTransaction::query()
            ->where('user_id', $driverId)
            ->where('connection_id', $connectionId)
            ->orderBy('create_date', 'desc')
            ->first();

        return $transaction;
    }

    private function loadBusinessDetails(int $offset): array
    {
        return BusinessDetails::toImport()
            ->limit(self::DEFAULT_CHUNK_SIZE)
            ->offset($offset)
            ->get()
            ->all();
    }

    private function countBusinessDetails(): int
    {
        return BusinessDetails::toImport()->count();
    }

    private function countLeaseCoTransactions(): int
    {
        return LeaseCoTransaction::query()->count();
    }

    private function loadLeaseCoTransactions(int $offset): array
    {
        if (is_null($this->exportedRequests)) {
            return LeaseCoTransaction::query()
                ->orderBy('id')
                ->limit(self::DEFAULT_CHUNK_SIZE)
                ->offset($offset)
                ->get()
                ->all();
        }

        if (empty($this->exportedRequests)) {
            return [];
        }

        $limit = self::DEFAULT_CHUNK_SIZE;
        $query = LeaseCoTransaction::query()->orderBy('id');

        while ($limit > 0 && !empty($this->exportedRequests)) {
            $values = array_shift($this->exportedRequests);
            $query->orWhere(fn ($query) =>
                $query->where('user_id', $values['driverId'])
                    ->where('connection_id', $values['connectionId']));
            $limit--;
        }

        return $query->get()->all();
    }

    private function getDriverEmail(int $driverId): string
    {
        // TODO: Fetch real driver email
        return 'driveremail@email.com';
    }

    private function getDriverName(int $driverId): string
    {
        // TODO: Fetch real driver name
        return 'Driver name';
    }

    private function getLeaseCoOrgIdByConnectionId(int $connectionId): string
    {
        if (empty($this->companies[$connectionId])) {
            $company = $this->getCompanyDetailsByConnectionId($connectionId);
            $this->companies[$connectionId] = [
                'name' => $company['name'] ?? '',
                'org_id' => $company['organization_id'] ?? '',
            ];
        }

        return $this->companies[$connectionId]['org_id'] ?? '';
    }

    private function getCompanyNameByConnectionId(int $connectionId): string
    {
        if (empty($this->companies[$connectionId])) {
            $company = $this->getCompanyDetailsByConnectionId($connectionId);
            $this->companies[$connectionId] = [
                'name' => $company['name'] ?? '',
                'org_id' => $company['organization_id'] ?? '',
            ];
        }

        return $this->companies[$connectionId]['name'] ?? '';
    }

    private function getCompanyDetailsByConnectionId(int $connectionId): ?array
    {
        return [
            'name' => 'Test org name',
            'organization_id' => 'Test org ID',
        ];
    }

    private function getConnectionName(int $connectionId): string
    {
        if (empty($this->connectionNames[$connectionId])) {
            $connection = DB::connection(Env::LocalCoulomb->value)
                ->selectOne("SELECT name FROM clb_connections where id = ?", [$connectionId]);
            $this->connectionNames[$connectionId] = $connection?->name;
        }

        return $this->connectionNames[$connectionId] ?? '';
    }

    private function getDriverCurrency(int $driverId): string
    {
        // TODO: Fetch real driver currency
        return 'EUR';
    }

    private function getCountryName(int $countryId): string
    {
        if (empty($this->countryNames[$countryId])) {
            // TODO: Fetch real country name
            $this->countryNames[$countryId] = "Some Country";
        }

        return $this->countryNames[$countryId] ?? '';
    }

    private function getStateIdByCountryIdAndStateCode(int $countryId, string $stateCode): ?int
    {
        if (empty($this->stateIds[$countryId][$stateCode])) {
            // TODO: Fetch real state ID
            $this->stateIds[$countryId][$stateCode] = 1;
        }

        return $this->stateIds[$countryId][$stateCode] ?? null;
    }

    private function getDriverVerificationValues(int $driverId, int $connectionId): array
    {
        $fields = $this->getConnectionFields($connectionId);
        // Create fields map (id => name)
        $fields = array_combine(
            array_map(fn ($field) => $field->id, $fields),
            array_map(fn ($field) => $field->name, $fields)
        );

        if (empty($fields)) {
            return [];
        }

        $values = $this->getDriverConnectionValues($driverId, $connectionId);
        // Create values map (field_id => value)
        $values = array_combine(
            array_map(fn ($value) => $value->company_connection_fields_id, $values),
            array_map(fn ($value) => $value->connection_value, $values)
        );

        $verfValues = [];

        foreach ($fields as $fieldId => $fieldName) {
            if (!isset($values[$fieldId])) {
                continue;
            }
            $verfValues[] = [
                'key' => $fieldName,
                'value' => $values[$fieldId]
            ];
        }

        return $verfValues;
    }

    private function getConnectionFields(int $connectionId): array
    {
        return DB::connection(Env::LocalCoulomb->value)
            ->select("SELECT * FROM clb_company_connection_fields where connection_id = ?", [$connectionId]);
    }

    private function getDriverConnectionValues(int $driverId, int $connectionId): array
    {
        return DB::connection(Env::LocalCoulomb->value)
            ->select("SELECT * FROM clb_driver_connection_values where driver_id = ? and connection_id = ?", [$driverId, $connectionId]);
    }

    // -----------------------------------------------------------------------------
    //------------------------- Local setup helper methods -------------------------
    // -----------------------------------------------------------------------------

    public function connect(): self
    {
        if (!connect(Env::LocalDms)) {
            $this->logMessage('Failed to connect to local DMS.', 'error');
            exit(1);
        }
        $this->logMessage('Connected to local DB');

        if (!connect(Env::LocalCoulomb)) {
            $this->logMessage('Failed to connect to local Coulomb.', 'error');
            exit(1);
        }
        $this->logMessage('Connected to local Coulomb');

        return $this;
    }

    public function createTables(bool $forceRecreate = false): self
    {
        $connection = Env::LocalDms->value;

        $tables = [
            'home_charger_requests' => 'V1_2_0__home_charger_requests.sql',
            'home_charger_requests_history' => 'V1_3_0__home_charger_requests_history.sql',
            'installer_jobs' => 'V1_5_0__installer_jobs.sql',
            'installer_jobs_history' => 'V1_6_0__installer_jobs_history.sql',
        ];

        if ($forceRecreate) {
            $this->logMessage('Dropping tables...');

            foreach (array_reverse(array_keys($tables)) as $table) {
                DB::schema($connection)->dropIfExists($table);
            }
        }

        foreach ($tables as $table => $migrationFile) {
            if (DB::schema($connection)->hasTable($table)) {
                $this->logMessage("Table $table already exists");
                continue;
            }

            $this->createTable(
                tableName: $table,
                migrationFile: $migrationFile,
                connection: $connection,
            );
        }

        return $this;
    }

    private function createTable(string $tableName, string $migrationFile, string $connection): void
    {
        if (DB::schema($connection)->hasTable($tableName)) {
            $this->logMessage(sprintf('Table %s already exists.', $tableName));
            return;
        }

        try {
            $migration = $this->readFromResources($migrationFile);
            $queries = explode(';', $migration);
            foreach ($queries as $query) {
                $query = trim($query);
                if (empty($query)) {
                    continue;
                }
                DB::connection($connection)->select($query);
            }
        } catch (\Exception $e) {
            $this->logMessage(sprintf('Failed to create table %s. Error: %s', $tableName, $e->getMessage()), 'error');
            exit(1);
        }

        $this->logMessage(sprintf('Table %s created.', $tableName));
    }

    private function readFromResources(string $filename): string
    {
        return file_get_contents($this->rootPath . '/scripts/src/Driser126/resources/' . $filename);
    }

    public function import(bool $freshImport = false): self
    {
        $destConnection = Env::LocalCoulomb->value;
        $tables = [
            'connections',
            'company_connection_fields',
            'company_driver_affiliation',
            'driver_connection_values',
            'business_details',
            'leaseco_transaction',
        ];

        if ($freshImport) {
            $this->logMessage('Dropping clb_leaseco_transaction and clb_business_details tables...');

            foreach (array_reverse($tables) as $table) {
                DB::schema($destConnection)->dropIfExists($table);
            }
        }

        foreach ($tables as $table) {
            // Create tables
            $this->createTable(
                tableName: $table,
                migrationFile: 'clb_' . $table . '.sql',
                connection: $destConnection,
            );
            $this->importData(
                tableName: $table,
                sourceFile: 'clb_' . $table . '.json',
                connection: $destConnection,
            );
        }

        return $this;
    }

    private function importData(string $tableName, string $sourceFile, string $connection): void
    {
        $fileSize = $this->countLines($this->rootPath . '/sources/driser126/' . $sourceFile);

        if (DB::connection($connection)->table($tableName)->count() === $fileSize) {
            $this->logMessage(sprintf('Table %s has same size as %s file.', $tableName, $sourceFile));
            return;
        }

        $startTime = microtime(true);

        $fs = fopen($this->rootPath . '/sources/driser126/' . $sourceFile, 'r');

        if (!$fs) {
            $this->logMessage(sprintf('Failed to open source file %s', $sourceFile), 'error');
            exit(1);
        }

        $line = fgets($fs);
        $lineNum = 0;
        $toInsert = [];

        while ($line !== false) {
            $line = trim($line, ',[]' . PHP_EOL);
            $data = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logMessage(sprintf('Failed to decode json line %s', $line), 'error');
                exit(1);
            }
            $lineNum++;
            $toInsert[] = array_map(fn($value) => is_string($value) && empty($value) ? null : $value, $data);
            if (count($toInsert) > 100) {
                DB::connection($connection)->table($tableName)->insert($toInsert);
                $toInsert = [];

                $this->logMessage(sprintf("Imported %s/%s lines to %s", $lineNum, $fileSize, $tableName));
            }

            $line = fgets($fs);
        }

        if (!empty($toInsert)) {
            DB::connection($connection)->table($tableName)->insert($toInsert);
        }

        fclose($fs);

        $this->logMessage(sprintf(
            "Imported %s/%s lines to %s. Time: %d second (s)",
            $lineNum, $fileSize, $tableName, microtime(true) - $startTime
        ));
    }

    private function countLines(string $filename): int
    {
        $fs = fopen($filename, 'r');
        $lines = 0;
        while (fgets($fs) !== false) {
            $lines++;
        }
        fclose($fs);
        return $lines;
    }

}
