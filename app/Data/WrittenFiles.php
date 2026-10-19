<?php

namespace App\Data;

/**
 * The files an inquiry write has put on disk so far.
 *
 * Handed to the service by the action so the action can remove them again when
 * the write fails part-way: a file lands on disk before the database knows
 * about it, so a rolled-back transaction cannot take it back on its own.
 */
class WrittenFiles
{
    /** @var list<string> */
    private array $paths = [];

    public function record(string $path): void
    {
        $this->paths[] = $path;
    }

    /** @return list<string> */
    public function paths(): array
    {
        return $this->paths;
    }
}
