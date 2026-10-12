<?php

namespace App\Actions\Saved;

use App\Models\User;
use App\Services\SavedStoneService;

class UnsaveStoneAction
{
    public function __construct(
        public readonly SavedStoneService $savedStoneService,
    ) {}

    public function execute(User $user, string $slug): void
    {
        $this->savedStoneService->remove($user, $slug);
    }
}
