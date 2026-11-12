<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One listed answer of a DetailField.
 *
 * A closed option (`is_suggestion` false) is one the client must pick, and an
 * answer stores it by id. A suggestion is a sample value shown beside the free
 * entry of a question that allows one: the answer stores the value itself,
 * and nothing checks it against the list. `links_to_stone` marks the "Same as
 * stone" option, which only a project with a stone row can take. One no longer
 * offered is made inactive, because sent answers reference it.
 *
 * @property int $id
 * @property int $detail_field_id
 * @property string $slug
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_suggestion
 * @property bool $links_to_stone
 * @property-read DetailField $detailField
 */
#[Fillable(['detail_field_id', 'slug', 'name', 'sort_order', 'is_active', 'is_suggestion', 'links_to_stone'])]
class DetailFieldOption extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_suggestion' => 'boolean',
            'links_to_stone' => 'boolean',
        ];
    }

    /** @return BelongsTo<DetailField, $this> */
    public function detailField(): BelongsTo
    {
        return $this->belongsTo(DetailField::class);
    }
}
