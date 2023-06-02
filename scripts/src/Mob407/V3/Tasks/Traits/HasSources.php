<?php

namespace App\Mob407\V3\Tasks\Traits;

trait HasSources
{
    protected array $sources = [];
    public function addSourceFile(string $key, string $filePath): self
    {
        $this->sources[$key] = $filePath;
        return $this;
    }

    protected function getSourceFile(string $key): string
    {
        if (!isset($this->sources[$key])) {
            throw new \InvalidArgumentException(sprintf('Source file "%s" not found', $key));
        }

        if (!file_exists($this->sources[$key])) {
            throw new \InvalidArgumentException(sprintf('Source file "%s" not found', $this->sources[$key]));
        }

        return $this->sources[$key];
    }
}
