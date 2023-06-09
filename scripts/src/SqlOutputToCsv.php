<?php

namespace App;

class SqlOutputToCsv
{
    public static function parseToFile(
        string $inputFile,
        string $outputFile,
    ): void {
        $fs = fopen($outputFile, 'w+');
        $skipLines = 0;
        $header = self::createHeader($inputFile);

        if (!empty($header)) {
            fputcsv($fs, $header);
            $skipLines = 2;
        }

        $fsInput = fopen($inputFile, 'r');

        $line = fgets($fsInput);

        while ($skipLines > 0) {
            $line = fgets($fsInput);
            $skipLines--;
        }

        while (false !== $line) {
            $parsed = self::parseLine($line);
            $line = fgets($fsInput);

            if (empty($parsed)) {
                continue;
            }

            fputcsv($fs, $parsed);
        }

        fclose($fs);
    }

    private static function createHeader(string $inputFile): array
    {
        $fs = fopen($inputFile, 'r');
        $line = fgets($fs);
        $line = trim($line);

        if (empty($line)) {
            fclose($fs);
            return [];
        }

        if ($line[0] === '=') {
            $line = fgets($fs);
        }

        if (empty($line)) {
            fclose($fs);
            return [];
        }

        $line = trim($line);
        fclose($fs);

        if ($line[0] !== '|') {
            return [];
        }

        return self::parseLine($line);
    }

    private static function parseLine(string $line): array
    {
        if (!str_contains($line, '|')) {
            return [];
        }

        $parsed = explode('|', $line);
        return array_map('trim', $parsed);
    }
}
