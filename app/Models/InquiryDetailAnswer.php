<?php

namespace App\Models;

use App\Enums\DetailAnswerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One project-scope answer of an inquiry, to one question its service asked.
 * Only the column matching the question's answer type is set: `text_value`,
 * `number_value`, `option_id` (a closed option) or `boolean_value`.
 *
 * @property int $inquiry_id
 * @property int $detail_field_id
 * @property string|null $text_value
 * @property string|null $number_value
 * @property int|null $option_id
 * @property bool|null $boolean_value
 * @property-read DetailField $detailField
 * @property-read DetailFieldOption|null $option
 */
#[Fillable(['detail_field_id', 'text_value', 'number_value', 'option_id', 'boolean_value'])]
class InquiryDetailAnswer extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'number_value' => 'decimal:2',
            'boolean_value' => 'boolean',
        ];
    }

    /**
     * The answer as the Inquire page sends it back: free text as it is, a
     * number, a closed option by its slug, yes or no as a boolean. Expects
     * the question and the option loaded.
     */
    public function formValue(): string|float|bool|null
    {
        return match ($this->detailField->answer_type) {
            DetailAnswerType::Text => $this->text_value,
            DetailAnswerType::Number => $this->number_value === null ? null : (float) $this->number_value,
            DetailAnswerType::Choice => $this->option?->slug,
            DetailAnswerType::Boolean => $this->boolean_value,
        };
    }

    /**
     * The answer as a reader is shown it: a number without trailing zeros, an
     * option by its name, yes or no. "Same as stone" reads as `$stoneName`, the
     * stone it means, when there is one. Expects the question and the option
     * loaded.
     */
    public function displayValue(?string $stoneName = null): string
    {
        return match ($this->detailField->answer_type) {
            DetailAnswerType::Text => (string) $this->text_value,
            DetailAnswerType::Number => str_contains((string) $this->number_value, '.') ? rtrim(rtrim((string) $this->number_value, '0'), '.') : (string) $this->number_value,
            DetailAnswerType::Choice => $this->option?->links_to_stone && $stoneName !== null ? $stoneName : (string) $this->option?->name,
            DetailAnswerType::Boolean => $this->boolean_value ? 'Yes' : 'No',
        };
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<DetailField, $this> */
    public function detailField(): BelongsTo
    {
        return $this->belongsTo(DetailField::class);
    }

    /** @return BelongsTo<DetailFieldOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(DetailFieldOption::class, 'option_id');
    }
}
