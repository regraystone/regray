<?php

namespace App\Services\Catalog;

use App\Catalog\CatalogListDefinition;
use App\Data\WrittenFiles;
use App\Enums\AuditEvent;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Support\CatalogImages;
use App\Support\PublicImageStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The writes of every list under Products (CatalogList): add an item, change
 * it, turn it on or off, archive it. Each is one transaction with its audit
 * row. A list's own rules and columns are its definition's.
 *
 * A thumbnail (stone types') goes on the public disk in the list's folder;
 * the one it replaces is deleted once the save has committed, unless some
 * row of the catalog still uses it (CatalogImages).
 *
 * Archiving is the soft delete, refused while anything on Regray uses the
 * item (the definition's `usage()`), so no product is left pointing at an
 * item the admin can no longer see.
 */
class CatalogListService
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly PublicImageStore $images,
    ) {}

    /**
     * @template TModel of Model
     *
     * @param  CatalogListDefinition<TModel>  $list
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(CatalogListDefinition $list, array $data, ?UploadedFile $thumbnail, User $by, ?string $ipAddress, ?string $userAgent): Model
    {
        $class = $list->model();
        /** @var TModel $item */
        $item = new $class;

        return $this->images->writing(function (WrittenFiles $written) use ($list, $item, $data, $thumbnail, $by, $ipAddress, $userAgent): Model {
            $list->fill($item, $data);

            if ($thumbnail !== null && $list->imageDirectory !== null) {
                $item->setAttribute('thumbnail_path', $this->images->store($thumbnail, $list->imageDirectory, $written));
            }

            return DB::transaction(function () use ($list, $item, $data, $by, $ipAddress, $userAgent): Model {
                $item->save();
                $list->sync($item, $data);
                $this->auditLogs->record($by->id, AuditEvent::Created, $item, null, $list->snapshot($item), $ipAddress, $userAgent);

                return $item;
            });
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  CatalogListDefinition<TModel>  $list
     * @param  TModel  $item
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function update(CatalogListDefinition $list, Model $item, array $data, ?UploadedFile $thumbnail, User $by, ?string $ipAddress, ?string $userAgent): Model
    {
        $before = $list->snapshot($item);
        $oldThumbnail = $list->imageDirectory !== null ? $item->getAttribute('thumbnail_path') : null;

        $this->images->writing(function (WrittenFiles $written) use ($list, $item, $data, $thumbnail, $before, $by, $ipAddress, $userAgent): void {
            $list->fill($item, $data);

            if ($thumbnail !== null && $list->imageDirectory !== null) {
                $item->setAttribute('thumbnail_path', $this->images->store($thumbnail, $list->imageDirectory, $written));
            }

            DB::transaction(function () use ($list, $item, $data, $before, $by, $ipAddress, $userAgent): void {
                $item->save();
                $list->sync($item, $data);
                $this->auditLogs->record($by->id, AuditEvent::Updated, $item, $before, $list->snapshot($item), $ipAddress, $userAgent);
            });
        });

        if ($thumbnail !== null && is_string($oldThumbnail)) {
            $this->images->delete([$oldThumbnail], CatalogImages::inUse([$oldThumbnail]));
        }

        return $item;
    }

    /**
     * @template TModel of Model
     *
     * @param  CatalogListDefinition<TModel>  $list
     * @param  TModel  $item
     */
    public function setActive(CatalogListDefinition $list, Model $item, bool $active, User $by, ?string $ipAddress, ?string $userAgent): void
    {
        if ((bool) $item->getAttribute('is_active') === $active) {
            return;
        }

        DB::transaction(function () use ($list, $item, $active, $by, $ipAddress, $userAgent): void {
            $before = $list->snapshot($item);
            $item->setAttribute('is_active', $active);
            $item->save();
            $this->auditLogs->record($by->id, AuditEvent::StatusChanged, $item, $before, $list->snapshot($item), $ipAddress, $userAgent);
        });
    }

    /**
     * Archive an item nothing on Regray uses; one in use is refused with why.
     *
     * @template TModel of Model
     *
     * @param  CatalogListDefinition<TModel>  $list
     * @param  TModel  $item
     *
     * @throws ValidationException
     */
    public function archive(CatalogListDefinition $list, Model $item, User $by, ?string $ipAddress, ?string $userAgent): void
    {
        DB::transaction(function () use ($list, $item, $by, $ipAddress, $userAgent): void {
            // Locked, so a product can't take it up between the check and the archive.
            $item->newQuery()->whereKey($item->getKey())->lockForUpdate()->first();
            $usage = $list->usage($item);

            if ($usage !== null) {
                throw ValidationException::withMessages(['archive' => __(':name can\'t be archived. :usage', ['name' => $list->name($item), 'usage' => $usage])]);
            }

            $before = $list->snapshot($item);
            $item->delete();
            $this->auditLogs->record($by->id, AuditEvent::Archived, $item, $before, $list->snapshot($item), $ipAddress, $userAgent);
        });
    }
}
