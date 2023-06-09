<?php

namespace App\Mob407\V3\Helpers;

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

    protected function readAsCommaSeparatedList(string $key): array
    {
        $result = [];
        $stream = fopen($this->getSourceFile($key), 'r');
        $line = fgets($stream);

        while ($line !== false) {
            if (str_starts_with($line, '#') || str_starts_with($line, '//')) {
                $line = fgets($stream);
                continue;
            }

            $items = explode(',', $line);
            $items = array_map('trim', $items);
            $items = array_filter($items, fn ($item) => !empty($item));
            $result = array_merge($result, $items);

            $line = fgets($stream);
        }

        fclose($stream);

        return $result;
    }
}
