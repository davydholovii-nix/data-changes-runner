<?php

namespace App;

use League\Csv\Reader;
use Symfony\Component\Console\Output\StreamOutput;

class ExtractUserIdsFromCsvReport
{
    public function __construct(private string $inputFile, private string $outputFile){}

    public function run()
    {
        $inputStream = fopen($this->inputFile, 'r');
        $outputStream = fopen($this->outputFile, 'w');
        $reader = Reader::createFromStream($inputStream)->setHeaderOffset(0);
        $output = new StreamOutput($outputStream);

        $userIds = [];
        foreach ($reader->getRecords() as $record) {
            if (isset($record['user_id'])) {
                $userIds[$record['user_id']] = $record['user_id'];
            }
        }

        $output->writeln(implode(',', array_unique($userIds)));

        return count($userIds);
    }
}
