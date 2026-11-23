<?php

namespace App\Http\Requests\Sales;

use App\Enums\SalesPeriod;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Sales page's span, from `?period=`. Anything it does not know is the
 * default, so a hand-edited URL shows the page rather than an error.
 */
class SalesReportRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }

    public function period(): SalesPeriod
    {
        return SalesPeriod::tryFrom((string) $this->query('period', '')) ?? SalesPeriod::DEFAULT;
    }
}
