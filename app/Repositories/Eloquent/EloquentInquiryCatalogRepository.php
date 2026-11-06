<?php

namespace App\Repositories\Eloquent;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\FurnitureType;
use App\Models\Service;
use App\Models\Stone;
use App\Repositories\Contracts\InquiryCatalogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentInquiryCatalogRepository implements InquiryCatalogRepositoryInterface
{
    public function services(): Collection
    {
        return Service::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'name', 'needs_furniture']);
    }

    public function furnitureTypes(): Collection
    {
        return FurnitureType::query()->active()->orderBy('sort_order')->get(['id', 'slug', 'name']);
    }

    public function stones(): Collection
    {
        return Stone::query()
            ->active()
            ->with(['category:id,slug,name,description', 'brand:id,slug,name'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function activeEdgeProfiles(): Collection
    {
        return EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'is_advisory']);
    }

    public function activeFinishes(): Collection
    {
        return Finish::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'is_advisory']);
    }
}
