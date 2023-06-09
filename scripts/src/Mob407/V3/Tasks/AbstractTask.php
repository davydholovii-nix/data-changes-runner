<?php

namespace App\Mob407\V3\Tasks;

use App\Mob407\V3\Helpers\HasLogger;
use Symfony\Component\Console\Output\Output;

abstract class AbstractTask
{
    use HasLogger;

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

    protected function getLogger(): Output
    {
        return $this->logger;
    }

    abstract public function run(): void;
}
