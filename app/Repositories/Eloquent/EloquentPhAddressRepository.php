<?php

namespace App\Repositories\Eloquent;

use App\Models\Ph\PhBarangay;
use App\Models\Ph\PhCity;
use App\Models\Ph\PhPostalCode;
use App\Models\Ph\PhProvince;
use App\Models\Ph\PhRegion;
use App\Repositories\Contracts\PhAddressRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EloquentPhAddressRepository implements PhAddressRepositoryInterface
{
    public function searchRegions(?string $pattern, int $perPage, int $page): Paginator
    {
        // By code, which is the PSGC's own order (Region I, II, ..., NCR, CAR, BARMM).
        return $this->page(
            PhRegion::query()->when($pattern !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('search_name', 'LIKE', $pattern)
                ->orWhereRaw('LOWER(designation) LIKE ?', [$pattern])))
                ->orderBy('code'),
            ['code', 'name', 'designation'],
            $perPage,
            $page,
        );
    }

    public function searchProvinces(string $regionCode, ?string $pattern, int $perPage, int $page): Paginator
    {
        return $this->page(
            PhProvince::query()->where('region_code', $regionCode)->when($pattern !== null, fn (Builder $query) => $query->where('search_name', 'LIKE', $pattern))->orderBy('name'),
            ['code', 'name', 'region_code'],
            $perPage,
            $page,
        );
    }

    public function searchCities(?string $provinceCode, ?string $regionCode, ?string $pattern, int $perPage, int $page): Paginator
    {
        $query = PhCity::query();

        if ($provinceCode !== null) {
            $query->where('province_code', $provinceCode);
        } else {
            $query->where('region_code', (string) $regionCode)->whereNull('province_code');
        }

        return $this->page(
            $query->when($pattern !== null, fn (Builder $query) => $query->where('search_name', 'LIKE', $pattern))->orderBy('name'),
            ['code', 'name', 'region_code', 'province_code', 'is_city'],
            $perPage,
            $page,
        );
    }

    public function searchBarangays(string $cityCode, ?string $pattern, int $perPage, int $page): Paginator
    {
        return $this->page(
            PhBarangay::query()->where('city_code', $cityCode)->when($pattern !== null, fn (Builder $query) => $query->where('search_name', 'LIKE', $pattern))->orderBy('name'),
            ['code', 'name', 'city_code'],
            $perPage,
            $page,
        );
    }

    public function postalCodesFor(string $cityCode): array
    {
        /** @var list<string> */
        return PhPostalCode::query()->where('city_code', $cityCode)->orderBy('postal_code')->pluck('postal_code')->all();
    }

    public function regionShapes(array $regionCodes): array
    {
        $withProvinces = PhProvince::query()->whereIn('region_code', $regionCodes)->distinct()->pluck('region_code')->all();
        $withLoneCities = PhCity::query()->whereIn('region_code', $regionCodes)->whereNull('province_code')->distinct()->pluck('region_code')->all();
        $shapes = [];

        foreach ($regionCodes as $code) {
            $shapes[$code] = [
                'hasProvinces' => in_array($code, $withProvinces, true),
                'hasCitiesWithoutProvince' => in_array($code, $withLoneCities, true),
            ];
        }

        return $shapes;
    }

    public function region(string $code): ?PhRegion
    {
        return PhRegion::query()->find($code);
    }

    public function province(string $code): ?PhProvince
    {
        return PhProvince::query()->find($code);
    }

    public function city(string $code): ?PhCity
    {
        return PhCity::query()->find($code);
    }

    public function barangay(string $code): ?PhBarangay
    {
        return PhBarangay::query()->find($code);
    }

    public function citiesNamed(array $names): array
    {
        return $names === [] ? [] : array_values(PhCity::query()->with('province')->whereIn('search_name', $names)->orderBy('code')->get()->all());
    }

    public function provincesNamed(array $names): array
    {
        return $names === [] ? [] : array_values(PhProvince::query()->whereIn('search_name', $names)->orderBy('code')->get()->all());
    }

    public function regionsNamed(array $names): array
    {
        return $names === [] ? [] : array_values(PhRegion::query()
            ->where(fn (Builder $query) => $query->whereIn('search_name', $names)->orWhereIn(DB::raw('LOWER(designation)'), $names))
            ->orderBy('code')->get()->all());
    }

    public function barangaysNamed(string $cityCode, array $names): array
    {
        return $names === [] ? [] : array_values(PhBarangay::query()->where('city_code', $cityCode)->whereIn('search_name', $names)->orderBy('code')->get()->all());
    }

    public function cityCodeForPostalCode(string $postalCode): ?string
    {
        $code = PhPostalCode::query()->where('postal_code', $postalCode)->value('city_code');

        return is_string($code) ? $code : null;
    }

    /**
     * One page, without counting the whole list: "more" is all the picker
     * needs to know.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Paginator<int, TModel>
     */
    private function page(Builder $query, array $columns, int $perPage, int $page): Paginator
    {
        return $query->simplePaginate($perPage, $columns, 'page', $page);
    }
}
