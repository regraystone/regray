<?php

namespace App\Models;

use App\Data\Measurement;
use App\Data\SizeMeasurement;
use App\Data\ThicknessMeasurement;
use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One stone of an inquiry, with the stone-scope answers its service asks.
 *
 * The slab size and thickness are kept as numbers and a unit rather than as
 * the string the form sends, so one size asked twice is one value; the
 * labels ("160 x 320 cm", "20 mm") are written back from them for every page
 * and the edit form.
 *
 * @property int $id
 * @property int $inquiry_id
 * @property int $stone_id
 * @property int|null $edge_profile_id Null when its service does not ask for an edge profile.
 * @property int|null $finish_id Null when its service does not ask for a finish.
 * @property int|null $quantity Null when its service does not ask how many pieces.
 * @property string|null $slab_width A decimal string; the three slab columns are null together, when its service does not ask for a slab size.
 * @property string|null $slab_length
 * @property SizeUnit|null $slab_unit
 * @property string|null $thickness A decimal string; null with its unit when its service does not ask for a thickness.
 * @property ThicknessUnit|null $thickness_unit
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['stone_id', 'edge_profile_id', 'finish_id', 'quantity', 'slab_width', 'slab_length', 'slab_unit', 'thickness', 'thickness_unit', 'sort_order'])]
class InquiryStone extends Model
{
    protected function casts(): array
    {
        return [
            'slab_width' => 'decimal:2',
            'slab_length' => 'decimal:2',
            'slab_unit' => SizeUnit::class,
            'thickness' => 'decimal:2',
            'thickness_unit' => ThicknessUnit::class,
        ];
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<Stone, $this> */
    public function stone(): BelongsTo
    {
        return $this->belongsTo(Stone::class);
    }

    /** @return BelongsTo<EdgeProfile, $this> */
    public function edgeProfile(): BelongsTo
    {
        return $this->belongsTo(EdgeProfile::class);
    }

    /** @return BelongsTo<Finish, $this> */
    public function finish(): BelongsTo
    {
        return $this->belongsTo(Finish::class);
    }

    /** "160 x 320 cm", or null when its service does not ask for a slab size. */
    public function slabSizeLabel(): ?string
    {
        if ($this->slab_width === null || $this->slab_length === null || $this->slab_unit === null) {
            return null;
        }

        return (new SizeMeasurement(Measurement::number($this->slab_width), Measurement::number($this->slab_length), $this->slab_unit))->label();
    }

    /** "20 mm", or null when its service does not ask for a thickness. */
    public function thicknessLabel(): ?string
    {
        if ($this->thickness === null || $this->thickness_unit === null) {
            return null;
        }

        return (new ThicknessMeasurement(Measurement::number($this->thickness), $this->thickness_unit))->label();
    }
}
