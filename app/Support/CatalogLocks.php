<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Holds the catalog rows a write is about to point at, so archiving one
 * can't slip in between the form's check and the write.
 *
 * The form's rules check that every chosen item is not archived, but only as
 * a read. CatalogListService::archive() locks the item FOR UPDATE and then
 * counts what uses it. A write that holds the same rows FOR SHARE inside its
 * transaction closes the gap from both sides: an archive that comes first
 * makes the write wait and then find the row archived (Postgres re-reads a
 * locked row's newest version), and a write that comes first makes the
 * archive wait until it commits and then count it.
 */
final class CatalogLocks
{
    /**
     * Lock the rows of `$table` with these ids FOR SHARE, refusing the write
     * when any is archived (or gone) by now. `$archivedColumn` is the table's
     * archive mark: the soft delete's, or its own `archived_at`.
     *
     * Call inside the write's transaction, before it writes.
     *
     * @param  iterable<int|null>  $ids
     *
     * @throws ValidationException With `$message` on `$field`.
     */
    public static function hold(string $table, iterable $ids, string $field, string $message, string $archivedColumn = 'deleted_at'): void
    {
        $wanted = [];

        foreach ($ids as $id) {
            if ($id !== null) {
                $wanted[(int) $id] = true;
            }
        }

        if ($wanted === []) {
            return;
        }

        $held = DB::table($table)
            ->whereIn('id', array_keys($wanted))
            ->whereNull($archivedColumn)
            ->orderBy('id')
            ->sharedLock()
            ->pluck('id');

        if ($held->count() !== count($wanted)) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
