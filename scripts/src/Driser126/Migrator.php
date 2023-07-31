<?php

namespace App\Driser126;

use App\Driser126\Models\BusinessDetails;
use App\Driser126\Models\HomeChargerRequest;
use App\Driser126\Models\InstallerJob;
use App\Driser126\Models\LeaseCoTransaction;
use Env;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\Console\Output\ConsoleOutput;

class Migrator
{
    private const DEFAULT_CHUNK_SIZE = 2;

    public function migrate(int $count = 0): void
    {
        $current = 0;
        $allRows = $count ?: $this->countLeaseCoTransactions();
        $this->logMessage(sprintf('Migrating %d records...', $allRows));

        foreach ($this->getLeaseCoTransactions($count) as $transaction) {
            $this->logMessage(sprintf(
                'Row %d/%d. Job ID: %d, External ID: %s, driver ID: %d, connection ID: %d',
                ++$current,
                $allRows,
                $transaction->id,
                $transaction->external_id ?: 'NULL',
                $transaction->user_id,
                $transaction->connection_id
            ));

            try {
                $this->processTransaction($transaction);
            } catch (\Exception $e) {
                $this->logMessage(sprintf(
                    'Error while processing transaction %d: %s',
                    $transaction->id,
                    $e->getMessage()
                ));
            }
        }

        $this->logMessage(sprintf("%d row(s) processed.", $allRows));
    }

    private function processTransaction(LeaseCoTransaction $transaction): void
    {
        $request = $this->recreateRequestAndHistory($transaction);
        $this->createInstallerJob($transaction, $request);
    }

    private function recreateRequestAndHistory(LeaseCoTransaction $transaction): HomeChargerRequest
    {
        /** @var BusinessDetails[]|Collection $allBusinessDetails */
        $allBusinessDetails = BusinessDetails::query()
            ->where('subscriber_id', $transaction->user_id)
            ->where('connection_id', $transaction->connection_id)
            ->orderBy('create_date')
            ->get();

        if ($allBusinessDetails->isEmpty()) {
            throw new \Exception(sprintf('Transaction %d has no business details.', $transaction->id));
        }

        /** @var null|HomeChargerRequest $request */
        $request = HomeChargerRequest::query()
            ->where('driver_id', $transaction->user_id)
            ->where('connection_id', $transaction->connection_id)
            ->first();

        if (!empty($request)) {
            $this->logMessage(sprintf("Driver %d has multiple installation jobs", $transaction->user_id));
        }

        foreach ($allBusinessDetails as $businessDetail) {
            if (is_null($request)) {
                // Creates APPROVED or DENIED request
                $request = $this->createPendingHomeChargerRequest($transaction, $businessDetail);
            }

            if ($businessDetail->connection_approval_date) {
                // Another home charger request
                $request->updated_at = $businessDetail->connection_approval_date;
                $request->request_status = 'APPROVED';
                $request->save();
            }

            if (empty($businessDetail->connection_discontinue_date)) {
                continue;
            }

            $request->updated_at = $businessDetail->connection_discontinue_date;
            // if request has never got approved then it had been denied right away
            $request->request_status = $businessDetail->connection_approval_date ? 'DELETED' : 'DENIED';
            $request->save();
        }

        return $request;
    }

    private function createPendingHomeChargerRequest(
        LeaseCoTransaction $transaction,
        BusinessDetails $businessDetails
    ): HomeChargerRequest {
        $request = new HomeChargerRequest();
        $request->connection_id = $transaction->connection_id;
        $request->driver_id = $transaction->user_id;
        $request->driver_group_id = $transaction->driver_group_id;
        $request->leaseco_org_name = $transaction->leaseco_org_name;
        $request->currency_iso_code = $transaction->currency_iso_code;
        $request->connection_name = $transaction->connection_name;

        $driverName = explode(' ', $transaction->driver_name);

        $request->driver_first_name = array_shift($driverName);
        $request->driver_last_name = implode(' ', $driverName);
        $request->email = $this->getDriverEmail($transaction);
        $request->leaseco_org_id = $businessDetails->company->organization_id;
        $request->company_id = $businessDetails->company_id;
        $request->phone_number = $businessDetails->contact_number;
        $request->dialing_code = $businessDetails->contact_number_dialing_code;
        $request->address1 = $businessDetails->address1;
        $request->address2 = $businessDetails->address2;
        $request->zip_code = $businessDetails->zipcode;
        $request->city = $businessDetails->city;
        $request->country_id = $businessDetails->country_id;
        $request->country_code = $businessDetails->country_code;
        $request->country_name = '';
        $request->state_id = 0;
        $request->state_code = $businessDetails->state_code;
        $request->state_name = $businessDetails->state;
        $request->created_at = $businessDetails->create_date;
        $request->updated_at = $businessDetails->create_date;
        $request->request_status = 'REQUESTED';

        $request->save();

        return $request;
    }

    private function createInstallerJob(
        LeaseCoTransaction $transaction,
        HomeChargerRequest $request
    ): InstallerJob {
        $installerJob = new InstallerJob();
        $installerJob->id = $transaction->id;
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

        $installerJob->save();

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

    // -----------------------------------------------------------------------------
    // ----------------------- Read/Write database methods -------------------------
    // -----------------------------------------------------------------------------

    /**
     * Reads leaseco transactions chunk (to not load all in memory) and returns a generator
     * From generator perspective it contains all the transactions, but they are not loaded
     * in memory all at once but slice by slice
     *
     * @param int $limit
     * @return \Generator|LeaseCoTransaction[]
     */
    private function getLeaseCoTransactions(int $limit): \Generator
    {
        static $processed = 0;
        static $offset = 0;
        static $count;
        static $loaded = [];

        if (is_null($count)) {
            $count = $limit ?: $this->countLeaseCoTransactions();
        }

        while ($processed < $count) {
            if (empty($loaded)) {
                $loaded = $this->loadLeaseCoTransactions($offset);
                $offset += count($loaded);

                if (empty($loaded)) {
                    break;
                }
            }

            $processed++;
            yield array_shift($loaded);
        }
    }

    private function countLeaseCoTransactions(): int
    {
        return LeaseCoTransaction::query()->count();
    }

    private function loadLeaseCoTransactions(int $offset): array
    {
        return LeaseCoTransaction::query()
            ->orderBy('id')
            ->limit(self::DEFAULT_CHUNK_SIZE)
            ->offset($offset)
            ->get()
            ->all();
    }

    private function getDriverEmail(LeaseCoTransaction $transaction): string
    {
        if (empty($transaction->userLogin?->email)) {
            $this->logMessage(sprintf("Transaction %d driver email is empty", $transaction->id), "warning");
        }

        return $transaction->userLogin?->email ?: '';
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
        $this->logMessage('Connected to local DMS.');
        if (!connect(Env::QA)) {
            $this->logMessage('Failed to connect to QA.', 'error');
            exit(1);
        }
        $this->logMessage('Connected to QA.');

        return $this;
    }

    public function createTables(bool $forceRecreate = false): self
    {
        if ($forceRecreate) {
            $this->logMessage('Dropping tables...');
            DB::schema(Env::LocalDms->value)->dropIfExists('installer_jobs_history');
            DB::schema(Env::LocalDms->value)->dropIfExists('installer_jobs');
            DB::schema(Env::LocalDms->value)->dropIfExists('home_charger_requests_history');
            DB::schema(Env::LocalDms->value)->dropIfExists('home_charger_requests');
        }

        $this->createTable(
            tableName: 'home_charger_requests',
            migrationFile: 'V1_2_0__home_charger_requests.sql'
        );
        $this->createTable(
            tableName: 'home_charger_requests_history',
            migrationFile: 'V1_3_0__home_charger_requests_history.sql'
        );
        $this->createTable(
            tableName: 'installer_jobs',
            migrationFile: 'V1_5_0__installer_jobs.sql'
        );
        $this->createTable(
            tableName: 'installer_jobs_history',
            migrationFile: 'V1_6_0__installer_jobs_history.sql'
        );

        return $this;
    }

    private function createTable(string $tableName, string $migrationFile): void
    {
        $schema = DB::schema(Env::LocalDms->value);

        if ($schema->hasTable($tableName)) {
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
                $schema->getConnection()->select($query);
            }
        } catch (\Exception $e) {
            $this->logMessage(sprintf('Failed to create table %s. Error: %s', $tableName, $e->getMessage()), 'error');
            exit(1);
        }

        $this->logMessage(sprintf('Table %s created.', $tableName));
    }

    private function readFromResources(string $filename): string
    {
        return file_get_contents(__DIR__ . '/resources/' . $filename);
    }
}
