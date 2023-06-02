<?php

namespace App\Mob407\V3\Tasks;

use Symfony\Component\Console\Output\Output;

abstract class AbstractTask
{
    protected function __construct(
        private readonly Output $logger,
        private readonly Output $consoleOutput,
    ) {}

    public static function init(Output $logger, Output $consoleOutput): static
    {
        return new static($logger, $consoleOutput);
    }

    protected function getOutput(): Output
    {
        return $this->consoleOutput;
    }

    protected function log(string $message, string $level = 'info'): void
    {
        $this->logger->writeln(sprintf('[%s] %s %s', date('Y-m-d H:i:s'), strtoupper($level), $message));
    }

    abstract public function run(): void;
}
