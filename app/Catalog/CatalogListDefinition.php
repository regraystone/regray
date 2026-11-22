<?php

namespace App\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Validator;

/**
 * One list products are built from, as its page under Products manages it:
 * what the table shows, what the drawer asks, the rules it checks, and
 * whether an item may be archived. CatalogListService does the writing, the
 * same way for every list: in a transaction, audited, the item archived (the
 * soft delete) only while nothing on Regray uses it.
 *
 * `fields()` describes the drawer's fields for the page to draw, so one page
 * serves every list. A field is
 * `{name, label, type, required?, hint?, maxLength?, step?, options?}`, its
 * type one of text, slug (a link's slug, made from the name while adding),
 * textarea, number, select (options `{value, label}`), multiselect (options
 * `{id, label}`), switch or image (the thumbnail).
 *
 * @template TModel of Model
 */
abstract class CatalogListDefinition
{
    /** Whether its items have an on/off switch (`is_active`). */
    public bool $hasStatus = false;

    /** Whether its items have a display order (`sort_order`) the admin sets. */
    public bool $hasOrder = false;

    /** The folder its thumbnails go in, or null when it has none. */
    public ?string $imageDirectory = null;

    /** The page's title, "Stone types". */
    abstract public function title(): string;

    /** One item, in a sentence: "stone type". */
    abstract public function singular(): string;

    /** What the page's subtitle says the list is for. */
    abstract public function subtitle(): string;

    /** @return class-string<TModel> */
    abstract public function model(): string;

    /**
     * Every item on the page, with what its row counts, in the list's order,
     * searched by `$escapedSearch` (escaped for ILIKE).
     *
     * @return Builder<TModel>
     */
    abstract public function query(?string $escapedSearch): Builder;

    /** @return list<array{key: string, header: string}> The table's columns after the first (the item's name). */
    abstract public function columns(): array;

    /** @return list<array<string, mixed>> */
    abstract public function fields(): array;

    /**
     * @param  TModel|null  $item  The item being changed, or null when one is being added.
     * @return array<string, array<int, mixed>>
     */
    abstract public function rules(?Model $item): array;

    /** @return array<string, string> */
    public function messages(): array
    {
        return [];
    }

    /**
     * A check the rules can't make on their own, run after them with the item
     * being changed (null when adding), or null when there is none.
     *
     * @return (\Closure(Validator, TModel|null): void)|null
     */
    public function after(): ?\Closure
    {
        return null;
    }

    /**
     * Its name, as the page and its toasts write it.
     *
     * @param  TModel  $item
     */
    abstract public function name(Model $item): string;

    /**
     * Its row's cells, by column key, and its form's values, by field name.
     *
     * @param  TModel  $item
     * @return array{cells: array<string, string>, values: array<string, mixed>, thumbnail?: string|null}
     */
    abstract public function row(Model $item): array;

    /**
     * Its own columns from the form's validated data.
     *
     * @param  TModel  $item
     * @param  array<string, mixed>  $data
     */
    abstract public function fill(Model $item, array $data): void;

    /**
     * Whatever else the form sets, once the item is saved.
     *
     * @param  TModel  $item
     * @param  array<string, mixed>  $data
     */
    public function sync(Model $item, array $data): void {}

    /**
     * Why it can't be archived, such as "Used by 12 stones", or null when
     * nothing on Regray uses it.
     *
     * @param  TModel  $item
     */
    abstract public function usage(Model $item): ?string;

    /**
     * What an audit row records of it.
     *
     * @param  TModel  $item
     * @return array<string, mixed>
     */
    abstract public function snapshot(Model $item): array;

    /**
     * Trim the form's text, before the rules read it.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function prepare(array $input): array
    {
        return array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $input);
    }

    /**
     * A translated sentence, as a string.
     *
     * @param  array<string, string>  $replace
     */
    protected static function say(string $key, array $replace = []): string
    {
        $line = __($key, $replace);

        return is_string($line) ? $line : $key;
    }

    /** "1 stone", "12 stones". */
    protected static function count(int $count, string $one, string $many): string
    {
        return $count.' '.($count === 1 ? $one : $many);
    }

    /** @return array<string, mixed> The display order field. */
    protected static function orderField(): array
    {
        return ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'step' => '1', 'hint' => 'Lower comes first. Left empty, it goes last.'];
    }

    /** @return array<string, mixed> The on/off field. */
    protected static function activeField(string $hint): array
    {
        return ['name' => 'is_active', 'label' => 'Offered on Regray', 'type' => 'switch', 'hint' => $hint];
    }

    /** @return array<string, array<int, mixed>> The rules for the display order and the switch. */
    protected function orderAndStatusRules(): array
    {
        return [
            ...($this->hasOrder ? ['sort_order' => ['nullable', 'integer', 'min:0', 'max:32000']] : []),
            ...($this->hasStatus ? ['is_active' => ['required', 'boolean']] : []),
        ];
    }

    /**
     * The display order and switch from the form, the order after the last
     * item when it is left empty.
     *
     * @param  array<string, mixed>  $data
     */
    protected function fillOrderAndStatus(Model $item, array $data): void
    {
        if ($this->hasOrder) {
            $order = $data['sort_order'] ?? null;
            $item->setAttribute('sort_order', $order === null || $order === ''
                ? ($item->exists ? $item->getAttribute('sort_order') : (int) $this->model()::query()->withoutGlobalScope(SoftDeletingScope::class)->max('sort_order') + 1)
                : (int) $order);
        }

        if ($this->hasStatus) {
            $item->setAttribute('is_active', filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN));
        }
    }
}
