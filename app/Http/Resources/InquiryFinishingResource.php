<?php

namespace App\Http\Resources;

use App\Models\EdgeProfile;
use App\Models\Finish;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An edge profile or a finish. `isAdvisory` marks the "Not sure yet, advise me"
 * option, which the flow also uses to fill in a stone that was picked as extra
 * information and so was never specified.
 *
 * @mixin EdgeProfile|Finish
 */
class InquiryFinishingResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, isAdvisory: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'isAdvisory' => $this->is_advisory,
        ];
    }
}
