<?php

namespace App\Http\Controllers\Address;

use App\Actions\Address\ResolveMapPlaceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Address\PhPlaceSearchRequest;
use App\Http\Requests\Address\ResolveMapPlaceRequest;
use App\Models\Ph\PhBarangay;
use App\Models\Ph\PhCity;
use App\Models\Ph\PhProvince;
use App\Models\Ph\PhRegion;
use App\Repositories\Contracts\PhAddressRepositoryInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;

/**
 * The searches behind the address picker, one level at a time, each under
 * its parent: never every barangay at once. A page is `data` and `nextPage`
 * (null on the last). The picker asks as the reader types, debounced.
 *
 * Every signed-in user may search: clients for their address book, staff for
 * the address they enter on an inquiry. The places are public reference data.
 */
class PhPlaceController extends Controller
{
    public function __construct(
        public readonly PhAddressRepositoryInterface $places,
    ) {}

    /**
     * The regions, each saying whether it has provinces (NCR has none) and
     * cities in no province, which is how the picker knows which to ask.
     */
    public function regions(PhPlaceSearchRequest $request): JsonResponse
    {
        $page = $this->places->searchRegions($request->pattern(), PhPlaceSearchRequest::PER_PAGE, $request->page());
        $shapes = $this->places->regionShapes(array_values(array_map(fn (PhRegion $region): string => $region->code, $page->items())));

        return $this->page($page, fn (PhRegion $region): array => [
            'code' => $region->code,
            'name' => $region->name,
            'designation' => $region->designation,
            ...$shapes[$region->code],
        ]);
    }

    public function provinces(PhPlaceSearchRequest $request): JsonResponse
    {
        $region = $request->code('region') ?? abort(422, 'Choose the region first.');

        return $this->page(
            $this->places->searchProvinces($region, $request->pattern(), PhPlaceSearchRequest::PER_PAGE, $request->page()),
            fn (PhProvince $province): array => ['code' => $province->code, 'name' => $province->name],
        );
    }

    /** The cities of a province, or of a region's cities in none (`region` alone). */
    public function cities(PhPlaceSearchRequest $request): JsonResponse
    {
        $province = $request->code('province');
        $region = $request->code('region');

        if ($province === null && $region === null) {
            abort(422, 'Choose the region or the province first.');
        }

        return $this->page(
            $this->places->searchCities($province, $region, $request->pattern(), PhPlaceSearchRequest::PER_PAGE, $request->page()),
            fn (PhCity $city): array => ['code' => $city->code, 'name' => $city->name, 'isCity' => $city->is_city],
        );
    }

    public function barangays(PhPlaceSearchRequest $request): JsonResponse
    {
        $city = $request->code('city') ?? abort(422, 'Choose the city or municipality first.');

        return $this->page(
            $this->places->searchBarangays($city, $request->pattern(), PhPlaceSearchRequest::PER_PAGE, $request->page()),
            fn (PhBarangay $barangay): array => ['code' => $barangay->code, 'name' => $barangay->name],
        );
    }

    /** Every ZIP code of a city: a handful at most, so one page. */
    public function postalCodes(PhPlaceSearchRequest $request): JsonResponse
    {
        $city = $request->code('city') ?? abort(422, 'Choose the city or municipality first.');

        return response()->json([
            'data' => array_map(fn (string $code): array => ['code' => $code, 'name' => $code], $this->places->postalCodesFor($city)),
            'nextPage' => null,
        ]);
    }

    /**
     * The address a point on the map stands for, from Mapbox's names for it:
     * every level it could match, and the levels it could not (`missed`).
     */
    public function resolve(ResolveMapPlaceRequest $request, ResolveMapPlaceAction $action): JsonResponse
    {
        return response()->json($action->execute($request->names(), $request->postalCode()));
    }

    /**
     * @template TModel
     *
     * @param  Paginator<int, TModel>  $page
     * @param  callable(TModel): array<string, mixed>  $row
     */
    private function page(Paginator $page, callable $row): JsonResponse
    {
        return response()->json([
            'data' => array_map($row, $page->items()),
            'nextPage' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }
}
