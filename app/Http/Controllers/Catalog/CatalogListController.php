<?php

namespace App\Http\Controllers\Catalog;

use App\Catalog\CatalogListDefinition;
use App\Enums\CatalogList;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogItemRequest;
use App\Http\Requests\DataTableRequest;
use App\Http\Requests\Product\ProductStatusRequest;
use App\Models\User;
use App\Services\Catalog\CatalogListService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The pages of the lists under Products (CatalogList), one page component
 * for all eight: a page of the list's items in its order, searched, and its
 * writes. Each write route checks its own permission on top of the page's,
 * the same ones as products'.
 *
 * `{id}` stays a plain id, looked up after the route's permission; an
 * archived item is gone from its page, so it is a 404.
 */
class CatalogListController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly CatalogListService $service) {}

    public function index(DataTableRequest $request, string $list): Response
    {
        $catalogList = CatalogList::from($list);
        $definition = $catalogList->definition();
        $status = $definition->hasStatus && in_array($request->query('status'), ['active', 'inactive'], true) ? (string) $request->query('status') : 'all';
        $paginator = $definition->query($request->escapedSearch())
            ->when($status !== 'all', fn ($query) => $query->where('is_active', $status === 'active'))
            ->paginate(self::PER_PAGE, ['*'], 'page', $request->page());
        $user = $request->user();
        $can = fn (Permission $permission): bool => $user?->can($permission->value) ?? false;

        return Inertia::render('catalog-list', [
            'list' => [
                'slug' => $catalogList->value,
                'title' => $definition->title(),
                'singular' => $definition->singular(),
                'subtitle' => $definition->subtitle(),
                'hasStatus' => $definition->hasStatus,
                'columns' => $definition->columns(),
                'fields' => $definition->fields(),
            ],
            'items' => [
                'data' => array_map(function (Model $item) use ($definition, $can): array {
                    $row = $definition->row($item);

                    return [
                        'id' => (int) $item->getKey(),
                        'name' => $definition->name($item),
                        'thumbnail' => $row['thumbnail'] ?? null,
                        'cells' => $row['cells'],
                        'values' => $row['values'],
                        'isActive' => $definition->hasStatus ? (bool) $item->getAttribute('is_active') : null,
                        'actions' => array_values(array_filter([
                            $can(Permission::ProductsUpdate) ? 'edit' : null,
                            $can(Permission::ProductsArchive) ? 'archive' : null,
                        ])),
                        'canToggle' => $can(Permission::ProductsUpdate),
                    ];
                }, $paginator->items()),
                'meta' => [
                    'currentPage' => $paginator->currentPage(),
                    'lastPage' => $paginator->lastPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => [...$request->filters(), 'perPage' => self::PER_PAGE, 'status' => $status],
            'canCreate' => $can(Permission::ProductsCreate),
            'limits' => ['thumbnailMaxKb' => config()->integer('products.thumbnail_max_kb')],
        ]);
    }

    public function store(CatalogItemRequest $request, string $list): RedirectResponse
    {
        $definition = $request->list();
        $item = $this->service->create($definition, $request->itemData(), $request->thumbnail(), self::actor($request), $request->ip(), $request->userAgent());

        return self::done(__(':name is added.', ['name' => $definition->name($item)]));
    }

    public function update(CatalogItemRequest $request, string $list, string $id): RedirectResponse
    {
        $definition = $request->list();
        /** @var Model $item The request found it, or it was a 404. */
        $item = $request->item();
        $this->service->update($definition, $item, $request->itemData(), $request->thumbnail(), self::actor($request), $request->ip(), $request->userAgent());

        return self::done(__(':name is updated.', ['name' => $definition->name($item)]));
    }

    public function status(ProductStatusRequest $request, string $list, string $id): RedirectResponse
    {
        $definition = CatalogList::from($list)->definition();
        abort_unless($definition->hasStatus, 404);
        $item = self::find($definition, $id);
        $active = $request->boolean('active');
        $this->service->setActive($definition, $item, $active, self::actor($request), $request->ip(), $request->userAgent());

        return self::done($active
            ? __(':name is offered again.', ['name' => $definition->name($item)])
            : __(':name is no longer offered.', ['name' => $definition->name($item)]));
    }

    public function archive(Request $request, string $list, string $id): RedirectResponse
    {
        $definition = CatalogList::from($list)->definition();
        $item = self::find($definition, $id);
        $this->service->archive($definition, $item, self::actor($request), $request->ip(), $request->userAgent());

        return self::done(__(':name is archived.', ['name' => Str::ucfirst($definition->name($item))]));
    }

    /**
     * An item still on its list's page, or a 404.
     *
     * @template TModel of Model
     *
     * @param  CatalogListDefinition<TModel>  $definition
     * @return TModel
     */
    public static function find(CatalogListDefinition $definition, string $id): Model
    {
        if (! ctype_digit($id)) {
            abort(404);
        }

        /** @var TModel */
        return $definition->model()::query()->findOrFail((int) $id);
    }

    private static function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private static function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
