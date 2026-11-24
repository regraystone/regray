<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Slugs made from a name, for rows whose slug never changes once made. */
final class Slugs
{
    /**
     * The name as a slug, numbered when another row of `$query` has it
     * already ("tiles", then "tiles-2"). Run it on a query that sees every
     * row, archived ones included, as the slug's unique index does.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function unique(Builder $query, string $name, int $max = 100, string $separator = '-'): string
    {
        $base = Str::limit(Str::slug($name, $separator), $max - 4, '') ?: 'item';
        $taken = (clone $query)->where('slug', 'like', addcslashes($base, '\\%_').'%')->pluck('slug')->all();

        $slug = $base;

        for ($number = 2; in_array($slug, $taken, true); $number++) {
            $slug = "{$base}{$separator}{$number}";
        }

        return $slug;
    }
}
