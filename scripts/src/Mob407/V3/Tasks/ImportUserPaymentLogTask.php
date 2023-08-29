<?php

namespace App\Mob407\V3\Tasks;

use App\Mob407\V3\Helpers\HasLogger;
use App\Mob407\V3\Helpers\HasSources;
use App\Mob407\V3\Helpers\Progress;
use App\Mob407\V3\Tasks\Helpers\JsonReader;
use Illuminate\Database\Capsule\Manager as DB;
use Symfony\Component\Console\Helper\ProgressBar;

class ImportUserPaymentLogTask extends AbstractTask
{
    use HasLogger;
    use HasSources;
    use JsonReader;

    public const SOURCE_CLB_USER_PAYMENT_LOG = 'user_payment_log';

    public function run(): void
    {
        $this->getOutput()->writeln("Processing table [" . self::SOURCE_CLB_USER_PAYMENT_LOG . "]...");
        $this->processTable(self::SOURCE_CLB_USER_PAYMENT_LOG);
        $this->getOutput()->writeln("Table [" . self::SOURCE_CLB_USER_PAYMENT_LOG . "] processed.");
    }

    private function processTable(string $tableName): void
    {
        $i = 1;
        $toInsert = [];

        while ($i < 10) {
            try {
                $filename = $this->getSourceFile($tableName . '_' . $i);
                $progress = Progress::init($this->getOutput(), $this->countLines($filename));

                foreach ($this->getJsonFileReader($filename) as $line) {
                    $progress->advance();

                    if ($line !== null) {
                        $toInsert[] = $this->normalize($line);
                    }

                    // Exit foreach
                    if (empty($toInsert)) {
                        break;
                    }

                    if (!empty($line) && count($toInsert) < 100) {
                        continue;
                    }

                    DB::table('user_payment_log')->insert($toInsert);
                    $toInsert = [];
                }


            } catch (\InvalidArgumentException $e) {
                $this->getOutput()->writeln("File with index [$i] not found. Considering all the files processed.");
                break;
            }

            $progress->finish(clean: true);

            $this->getOutput()->writeln("File $filename processed.");

            $i++;
        }
    }

    private function normalize(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value === '') {
                $row[$key] = null;
            }
        }

        return $row;
    }
}
