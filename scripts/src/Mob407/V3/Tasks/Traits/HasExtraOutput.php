<?php

namespace App\Mob407\V3\Tasks\Traits;

use Symfony\Component\Console\Output\StreamOutput;

trait HasExtraOutput
{
    protected array $extraOutput = [];

    /**
     * @var array <string, StreamOutput>
     */
    protected array $extraWriters = [];

    abstract protected function log(string $message, string $level = 'info'): void;

    public function addExtraOutput(string $key, string $filePath): self
    {
        $this->extraOutput[$key] = $filePath;
        return $this;
    }

    protected function writeExtra(string $key, string $message): void
    {
        if (!isset($this->extraOutput[$key])) {
            throw new \InvalidArgumentException(sprintf('Extra output file "%s" not found', $key));
        }

        $filePath = $this->extraOutput[$key];
        if (!isset($this->extraWriters[$key])) {
            $stream = fopen($filePath, 'a');
            if (false === $stream) {
                throw new \RuntimeException(sprintf('Unable to open file "%s"', $filePath));
            }

            $this->extraWriters[$key] = new StreamOutput($stream);
        }

        $this->extraWriters[$key]->write($message);
    }

    public function __destruct()
    {
        foreach ($this->extraWriters as $key => $writer) {
            /** @var StreamOutput $writer */
            fclose($writer->getStream());

            $this->log(sprintf('File %s closed', $key), 'info');
        }
    }
}
