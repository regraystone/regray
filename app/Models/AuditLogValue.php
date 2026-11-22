<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

/**
 * One value of an audit entry: a column's old or new value, or, for a nested
 * value, one leaf of it, by its dotted path (`slug`, `gallery.1`,
 * `answers.0.value`).
 *
 * The value is kept as text with its type, so it reads back as written: a
 * `string`, `integer`, `float` or `boolean` has its value (a boolean as 1 or
 * 0); a `null` and an empty `array` have none. A side that was an empty array
 * is one `array` row with an empty path. Values are read as JSON would have
 * kept them (an enum as its value, a date as its ISO string), and a key with
 * a dot in it would read back nested, which no audited key has.
 *
 * @property int $id
 * @property int $audit_log_id
 * @property string $side `old` or `new`.
 * @property string $path
 * @property string $value_type
 * @property string|null $value
 */
#[Fillable(['audit_log_id', 'side', 'path', 'value_type', 'value'])]
class AuditLogValue extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<AuditLog, $this> */
    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class);
    }

    /**
     * The rows that keep one side of an entry's values.
     *
     * @param  array<array-key, mixed>  $values
     * @return list<array{side: string, path: string, value_type: string, value: string|null}>
     */
    public static function rowsFor(string $side, array $values): array
    {
        /** @var array<array-key, mixed> $values */
        $values = json_decode(json_encode($values, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        if ($values === []) {
            return [self::row($side, '', [])];
        }

        $rows = [];
        self::walk($side, '', $values, $rows);

        return $rows;
    }

    /**
     * One side of an entry's values from its rows, in the order they were
     * written, or null when the side has none.
     *
     * @param  iterable<AuditLogValue|\stdClass>  $rows  Models, or a query's plain rows
     * @return array<array-key, mixed>|null
     */
    public static function restore(iterable $rows): ?array
    {
        $values = null;

        foreach ($rows as $row) {
            $values ??= [];
            $value = match ($row->value_type) {
                'string' => (string) $row->value,
                'integer' => (int) $row->value,
                'float' => (float) $row->value,
                'boolean' => $row->value === '1',
                'array' => [],
                default => null,
            };

            if ($row->path === '') {
                continue;
            }

            Arr::set($values, $row->path, $value);
        }

        return $values;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<array{side: string, path: string, value_type: string, value: string|null}>  $rows
     */
    private static function walk(string $side, string $prefix, array $values, array &$rows): void
    {
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value) && $value !== []) {
                self::walk($side, $path, $value, $rows);
            } else {
                $rows[] = self::row($side, $path, $value);
            }
        }
    }

    /** @return array{side: string, path: string, value_type: string, value: string|null} */
    private static function row(string $side, string $path, mixed $value): array
    {
        [$type, $text] = match (true) {
            is_string($value) => ['string', $value],
            is_int($value) => ['integer', (string) $value],
            is_float($value) => ['float', json_encode($value, JSON_THROW_ON_ERROR)],
            is_bool($value) => ['boolean', $value ? '1' : '0'],
            is_array($value) => ['array', null],
            default => ['null', null],
        };

        return ['side' => $side, 'path' => $path, 'value_type' => $type, 'value' => $text];
    }
}
