<?php

namespace App\Mob407\V3\Helpers;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\Output;

class Progress
{
    private ProgressBar $progress;
    private Output $out;

    private function __construct(Output $out, int $max)
    {
        $this->progress = new ProgressBar($out, $max);
        $this->progress->setFormat("\r%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%");
        $this->progress->start();

        $this->out = $out;
    }
    public static function init(Output $out, int $max): self
    {
        return new self($out, $max);
    }

    public function advance(int $count = 1): self
    {
        $this->progress->advance($count);
        return $this;
    }

    public function finish(bool $clean = false): void
    {
        $this->progress->finish();

        if ($clean) {
            $this->out->write("\x0D"); // Move the cursor to the beginning of the line
            $this->out->write("\x1B[2K"); // Clear the entire line
        }
    }
}
