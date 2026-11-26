<?php

namespace App\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Validator;

/**
 * One list, as its page manages it (under Products or Services, CatalogList):
 * what the table shows, what the drawer asks, the rules it checks, and
 * whether an item may be archived. CatalogListService does the writing, the
 * same way for every list: in a transaction, audited, the item archived only
 * while nothing on Regray uses it. Archiving is the soft delete, unless the
 * list keeps its own mark (`live()`, `archiveItem()`).
 *
 * `fields()` describes the drawer's fields for the page to draw, so one page
 * serves every list. A field is
 * `{name, label, type, required?, hint?, maxLength?, maxItems?, step?, options?, showWhen?}`,
 * its type one of text, slug (a link's slug, made from the name while
 * adding), textarea, number, select (options `{value, label}`), multiselect
 * (options `{id, label}`), tags (a short list of words, up to `maxItems`),
 * switch or image (the thumbnail). `showWhen: {field, equals}` shows it only
 * while another field has that value (`{field, in}`: one of those values). A field an item's edit doesn't offer
 * is in its row's `fixed`.
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
     * Its items still on the page, archived ones left out: the soft delete
     * leaves them out by itself; a list with its own mark overrides this.
     *
     * @return Builder<TModel>
     */
    public function live(): Builder
    {
        return $this->model()::query();
    }

    /**
     * Archive it, once nothing uses it: the soft delete, unless the list
     * keeps its own mark.
     *
     * @param  TModel  $item
     */
    public function archiveItem(Model $item): void
    {
        $item->delete();
    }

    /**
     * What its page calls the switch: the column, the filter's two states,
     * and the toggle's two actions.
     *
     * @return array{header: string, on: string, off: string, turnOn: string, turnOff: string}
     */
    public function statusWords(): array
    {
        return ['header' => 'Offered', 'on' => 'Offered', 'off' => 'Not offered', 'turnOn' => 'Offer', 'turnOff' => 'Stop offering'];
    }

    /**
     * The toast once its switch is flipped.
     *
     * @param  TModel  $item
     */
    public function statusMessage(Model $item, bool $active): string
    {
        return $active
            ? self::say(':name is offered again.', ['name' => $this->name($item)])
            : self::say(':name is no longer offered.', ['name' => $this->name($item)]);
    }

    /** What the Archive question says happens, and when it is refused. */
    public function archiveDescription(): string
    {
        return self::say('It leaves this list and can no longer be chosen; past inquiries keep it. A :item a product or stone type still uses can\'t be archived.', ['item' => $this->singular()]);
    }

    /** What the empty list suggests. */
    public function emptyDescription(): string
    {
        return self::say('Add a :item for products to use.', ['item' => $this->singular()]);
    }

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
     * Its row's cells, by column key, its form's values, by field name, and
     * the fields its edit doesn't offer (`fixed`), if any.
     *
     * @param  TModel  $item
     * @return array{cells: array<string, string>, values: array<string, mixed>, thumbnail?: string|null, fixed?: list<string>}
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

    /**
     * A rule that the value is not already some other item's `$column`,
     * ignoring case, as a unique `lower(...)` index holds it: among every
     * item, archived ones included, or only the ones not archived when the
     * index leaves those out.
     *
     * @param  literal-string  $column
     * @param  TModel|null  $item  The item being changed, or null when one is being added.
     * @return \Closure(string, mixed, \Closure(string): mixed): void
     */
    protected function uniqueIgnoringCase(string $column, ?Model $item, string $message, bool $archivedToo = true): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($column, $item, $message, $archivedToo): void {
            if (! is_string($value)) {
                return;
            }

            $taken = $this->model()::query()
                ->when($archivedToo, fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class))
                ->whereRaw("lower({$column}) = ?", [mb_strtolower($value)])
                ->when($item !== null, fn (Builder $query) => $query->whereKeyNot($item->getKey()))
                ->exists();

            if ($taken) {
                $fail($message);
            }
        };
    }

    /** @return array<string, mixed> The display order field. */
    protected static function orderField(): array
    {
        return ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'step' => '1', 'hint' => 'Lower comes first. Left empty, a new one goes last and one already listed keeps its place.'];
    }

    /** @return array<string, mixed> The on/off field. */
    protected static function activeField(string $hint, string $label = 'Offered on Regray'): array
    {
        return ['name' => 'is_active', 'label' => $label, 'type' => 'switch', 'hint' => $hint];
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
     * The display order and switch from the form. Left empty, a new item goes
     * after the last one and an existing item keeps its order.
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
