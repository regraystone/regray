<?php

namespace App\Actions\Saved;

use App\Models\Stone;
use App\Models\User;
use App\Services\SavedStoneService;
use Illuminate\Support\Collection;

class ListSavedStonesAction
{
    public function __construct(
        public readonly SavedStoneService $savedStoneService,
    ) {}

    /**
     * @return array{saved: Collection<int, Stone>}
     */
    public function execute(User $user): array
    {
        return $this->savedStoneService->listFor($user);
    }
}
