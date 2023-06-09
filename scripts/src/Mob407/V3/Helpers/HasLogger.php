<?php

namespace App\Mob407\V3\Helpers;

use Symfony\Component\Console\Output\Output;

trait HasLogger
{
    abstract protected function getLogger(): Output;

    protected function log(string $message, string $level = 'info'): void
    {
        $this->getLogger()->writeln(sprintf('[%s] %s %s', date('Y-m-d H:i:s'), strtoupper($level), $message));
    }
}
