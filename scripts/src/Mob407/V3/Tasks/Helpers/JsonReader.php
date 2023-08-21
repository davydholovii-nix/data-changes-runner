<?php

namespace App\Mob407\V3\Tasks\Helpers;

trait JsonReader
{
    protected function countLines(string $filePath): int
    {
        $file = fopen($filePath, 'r');
        $lineCount = 0;
        while (!feof($file)) {
            $line = fgets($file);
            if ($line !== false) {
                $lineCount++;
            }
        }
        fclose($file);

        return $lineCount;
    }

    protected function getJsonFileReader(string $filePath): \Generator
    {
        $file = fopen($filePath, 'r');


        while (!feof($file)) {
            $line = fgets($file);
            if ($line !== false) {
                $line = $this->clearLine($line);
                yield json_decode($line, true);
            }
        }
        fclose($file);

        yield null;
    }

    private function clearLine(string $line): string
    {
        return trim(trim($line, "\n\r\0\x0B"), ",[]");
    }
}
