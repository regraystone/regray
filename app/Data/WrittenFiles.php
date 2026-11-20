<?php

namespace App\Data;

/**
 * The files a write has put on disk so far: an inquiry's, or a brand's
 * images (BrandAdminService).
 *
 * Kept so the write can remove them again when it fails part-way: a file
 * lands on disk before the database knows about it, so a rolled-back
 * transaction cannot take it back on its own.
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
