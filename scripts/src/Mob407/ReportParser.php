<?php

namespace App\Mob407;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Parses CSV report created from Excel file generated previously by this script
 * and extracts Driver IDs from it.
 */
class ReportParser
{
    public function __construct(
        private string $sourcesDir,
        private string $outputDir,
    ) {
    }

    public function parse(array $files): void
    {
        $consoleOutput = new ConsoleOutput();

        foreach ($files as $file) {
            $consoleOutput->writeln(sprintf('Parsing file: %s', $file));
            $this->parseFile(
                $this->sourcesDir . '/' . $file,
                $this->outputDir . '/' . $file . '.parsed.txt',
                $consoleOutput,
            );
            $consoleOutput->writeln(sprintf('File result saved: %s', $this->outputDir . '/' . $file . '.parsed.txt',));
        }
    }

    private function parseFile(string $file, string $saveResultAs, Output $output): string
    {
        $resultOutput = new StreamOutput(fopen($saveResultAs, 'w+'));
        $progress = new ProgressBar($output, $this->countLines($file));

        $fs = fopen($file, 'r');
        $line = fgets($fs);
        $progress->advance();
        $lineCounter = 0;

        while ($line !== false) {
            if (str_contains($line, 'Driver ID')) {
                $lineElements = explode(',', $line);
                $resultOutput->write(sprintf('%s,', $lineElements[1]));

                $lineCounter++;
                if ($lineCounter === 10) {
                    $resultOutput->writeln('');
                    $lineCounter = 0;
                }
            }

            $progress->advance();
            $line = fgets($fs);
        }

        $progress->finish();
        $output->write("\r" . str_repeat(" ", 100) . "\r");

        return $saveResultAs;
    }

    private function countLines(string $file): int
    {
        $linesCount = 0;

        $fs = fopen($file, 'r');
        $line = fgets($fs);

        while ($line !== false) {
            $linesCount++;
            $line = fgets($fs);
        }

        return $linesCount;
    }
}
