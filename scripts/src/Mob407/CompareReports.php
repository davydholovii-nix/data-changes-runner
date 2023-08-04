<?php

namespace App\Mob407;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\DiffOnlyOutputBuilder;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class CompareReports
{
    private readonly Differ $differ;
    private readonly OutputInterface $output;
    public function __construct(private array $resportPairs)
    {
        $builder = new DiffOnlyOutputBuilder();
        $this->differ = new Differ($builder);
        $this->output = new ConsoleOutput();
    }

    public function run()
    {
        foreach ($this->resportPairs as $reportPair) {
            $this->output->writeln(printf('Comparing files %s and %s', $reportPair[0], $reportPair[1]));

            $diff = $this->differ->diff(
                file_get_contents($reportPair[0]),
                file_get_contents($reportPair[1]),
            );

            $this->output->writeln($diff);
        }
    }
}
