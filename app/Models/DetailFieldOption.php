<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

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
 * A closed option may also carry a `description` (shown once it is picked)
 * and a picture (`thumbnail_path`: a question whose choices have pictures is
 * asked with picture cards). `is_custom` asks the client to describe their
 * own ("Custom shape"), `is_advisory` is "Not sure yet, advise me", and a
 * table shape's `size_kind` says how its size is asked (SIZE_KINDS). It is
 * offered for the services listed in `services`, or for every service that
 * asks its question when none is listed.
 *
 * A closed option is a question's listing of a library choice (`choice_id`,
 * Choice): its name, description, picture, kind and services are the
 * choice's, written here when the choice is saved, while its slug, order and
 * the answers that chose it are the question's own. The Choices page under
 * Services edits the choice; the Questions page picks a question's choices.
 * One taken off its question is archived (`archived_at`).
 *
 * @property int $id
 * @property int $detail_field_id
 * @property int|null $choice_id
 * @property string $slug
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_suggestion
 * @property bool $links_to_stone
 * @property string|null $description
 * @property string|null $thumbnail_path
 * @property bool $is_custom
 * @property bool $is_advisory
 * @property string|null $size_kind
 * @property Carbon|null $archived_at
 * @property-read DetailField $detailField
 * @property-read Choice|null $choice
 * @property-read Collection<int, Service> $services
 */
#[Fillable(['detail_field_id', 'choice_id', 'slug', 'name', 'sort_order', 'is_active', 'is_suggestion', 'links_to_stone', 'description', 'thumbnail_path', 'is_custom', 'is_advisory', 'size_kind'])]
class DetailFieldOption extends Model
{
    /** A round table's size is its diameter. */
    public const SIZE_DIAMETER = 'diameter';

    /** A rectangular, square or oval table's size is its width and length. */
    public const SIZE_WIDTH_LENGTH = 'width_length';

    public const SIZE_KINDS = [self::SIZE_DIAMETER, self::SIZE_WIDTH_LENGTH];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_suggestion' => 'boolean',
            'links_to_stone' => 'boolean',
            'is_custom' => 'boolean',
            'is_advisory' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DetailField, $this> */
    public function detailField(): BelongsTo
    {
        return $this->belongsTo(DetailField::class);
    }

    /** @return BelongsTo<Choice, $this> The library choice it lists. */
    public function choice(): BelongsTo
    {
        return $this->belongsTo(Choice::class);
    }

    /** @return BelongsToMany<Service, $this> The services it is offered for; none is every one. */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'detail_field_option_service');
    }

    /**
     * Whether it is offered for this service: it lists none (every service
     * that asks the question), or lists this one. Expects `services` loaded.
     */
    public function isOfferedFor(?int $serviceId): bool
    {
        return $serviceId === null || $this->services->isEmpty() || $this->services->contains('id', $serviceId);
    }
}
