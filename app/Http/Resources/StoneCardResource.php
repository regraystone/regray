<?php

namespace App\Http\Resources;

use App\Models\Size;
use App\Models\Stone;
use App\Models\StoneVariant;
use App\Models\Thickness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A stone as a card. On the Inquire page (and its edit) the card also carries
 * the slabs the stone comes in, which a stone row lists ahead of Other; they
 * are there only where the variants were loaded, so a brand or saved page
 * pays nothing for them.
 *
 * `variants` are its slabs as labels (`size` null for one sold without set
 * sizes), from which the page narrows the thicknesses to the chosen size.
 * `sizes` and `thicknesses` are the same slabs' sizes and thicknesses, each
 * once, by unit then by number; an option's `value` is its label ("20 mm",
 * "160 x 320 cm"), the string a row sends and the server parses, whether it
 * was picked here or typed under Other. `sizeNote` says how big the pieces run
 * when there are no set sizes ("Diameter 15–40 cm").
 *
 * @mixin Stone
 */
class StoneCardResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, thumbnail: string, brand: array{slug: string, name: string, archived?: true}, type: array{slug: string, name: string}, variants?: mixed, sizes?: mixed, thicknesses?: mixed, sizeNote?: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'thumbnail' => ShowcaseCardResource::resolveThumbnailUrl($this->thumbnail_path),
            // `archived` only for an archived brand's, which only a saved card shows: its page is gone.
            'brand' => ['slug' => $this->brand->slug, 'name' => $this->brand->name, ...($this->brand->archived_at !== null ? ['archived' => true] : [])],
            'type' => ['slug' => $this->type->slug, 'name' => $this->type->name],
            'variants' => $this->whenLoaded('variants', fn (): array => array_values($this->variants
                ->map(fn (StoneVariant $variant): array => ['size' => $variant->size?->label, 'thickness' => $variant->thickness->label])
                ->all())),
            'sizes' => $this->whenLoaded('variants', fn (): array => self::options($this->variants
                ->map(fn (StoneVariant $variant): ?Size => $variant->size)
                ->filter()
                ->unique('id')
                ->sort(fn (Size $left, Size $right): int => [$left->sort_order, (float) $left->width, (float) $left->length] <=> [$right->sort_order, (float) $right->width, (float) $right->length])
                ->map(fn (Size $size): string => $size->label)
                ->all())),
            'thicknesses' => $this->whenLoaded('variants', fn (): array => self::options($this->variants
                ->map(fn (StoneVariant $variant): Thickness => $variant->thickness)
                ->unique('id')
                ->sort(fn (Thickness $left, Thickness $right): int => [$left->sort_order, (float) $left->value] <=> [$right->sort_order, (float) $right->value])
                ->map(fn (Thickness $thickness): string => $thickness->label)
                ->all())),
            'sizeNote' => $this->whenLoaded('variants', fn (): ?string => $this->size_note),
        ];
    }

    /**
     * Labels as a list's options, each its own value.
     *
     * @param  array<array-key, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $labels): array
    {
        return array_values(array_map(fn (string $label): array => ['value' => $label, 'label' => $label], $labels));
    }
}
