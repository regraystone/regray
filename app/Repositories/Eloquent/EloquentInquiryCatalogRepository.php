<?php

namespace App\Repositories\Eloquent;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\ProjectCategory;
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
            ->with('features:id,service_id,body,sort_order')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function stones(): Collection
    {
        return Stone::query()
            ->active()
            ->with('category:id,slug,name,description,origin')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function applications(): Collection
    {
        return ProjectCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'image_path']);
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
