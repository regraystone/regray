<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Which image files the catalog still points at. A seeded stone type can share
 * its thumbnail with a stone, and a stone its thumbnail with a gallery photo,
 * so a file the Products pages replace or take off is deleted only once no
 * row anywhere in the catalog uses it.
 */
final class CatalogImages
{
    /** @var array<string, string> Each table's image column. */
    private const COLUMNS = [
        'stones' => 'thumbnail_path',
        'stone_images' => 'path',
        'stone_types' => 'thumbnail_path',
        'furniture' => 'thumbnail_path',
        'furniture_images' => 'path',
    ];

    /**
     * The ones of `$paths` some row still uses, archived rows included (they
     * keep their files).
     *
     * @param  list<string|null>  $paths
     * @return list<string>
     */
    public static function inUse(array $paths): array
    {
        $paths = array_values(array_unique(array_filter($paths, fn (?string $path): bool => $path !== null && $path !== '')));

        if ($paths === []) {
            return [];
        }

        $used = [];

        foreach (self::COLUMNS as $table => $column) {
            /** @var list<string> $found */
            $found = DB::table($table)->whereIn($column, $paths)->distinct()->pluck($column)->all();
            $used = [...$used, ...$found];
        }

        return array_values(array_unique($used));
    }
}
