<?php

namespace App\Services\Product;

use App\Data\WrittenFiles;
use App\Models\FurnitureImage;
use App\Models\StoneImage;
use App\Support\PublicImageStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * A product's gallery as the Products page edits it: its photos besides the
 * thumbnail, in order, each a saved photo kept by its id or a new file.
 *
 * New files are stored before the product's transaction (store()), then the
 * rows are made to match the form inside it (arrange()). A photo left out is
 * taken off; its file goes once the save has committed, which the service
 * does with the paths arrange() returns. The rows are renumbered through
 * negative places first, as (product, sort_order) is unique.
 */
final class ProductGallery
{
    public function __construct(private readonly PublicImageStore $images) {}

    /**
     * Store the gallery's new photos under `$directory`, by the row they are on.
     *
     * @param  list<array{id: int|null, file: UploadedFile|null}>  $gallery
     * @return array<int, string>
     */
    public function store(array $gallery, string $directory, WrittenFiles $written): array
    {
        $paths = [];

        foreach ($gallery as $order => $row) {
            if ($row['file'] !== null) {
                $paths[$order] = $this->images->store($row['file'], $directory, $written);
            }
        }

        return $paths;
    }

    /**
     * Make the gallery `$gallery`, in its order, from the new photos stored
     * (`$stored`, by row). Returns the paths of the photos taken off.
     *
     * @template TImage of StoneImage|FurnitureImage
     *
     * @param  HasMany<TImage, covariant Model>  $images
     * @param  list<array{id: int|null, file: UploadedFile|null}>  $gallery
     * @param  array<int, string>  $stored
     * @return list<string>
     */
    public function arrange(HasMany $images, array $gallery, array $stored): array
    {
        $kept = array_values(array_filter(array_column($gallery, 'id')));
        $removed = (clone $images)->whereNotIn('id', $kept)->pluck('path')->all();
        (clone $images)->whereNotIn('id', $kept)->delete();

        // Out of the way first: (product, sort_order) is unique.
        (clone $images)->update(['sort_order' => DB::raw('-1 - sort_order')]);

        foreach ($gallery as $order => $row) {
            if ($row['id'] !== null) {
                (clone $images)->whereKey($row['id'])->update(['sort_order' => $order]);

                continue;
            }

            (clone $images)->create(['path' => $stored[$order], 'sort_order' => $order]);
        }

        return array_values(array_map(fn (mixed $path): string => (string) $path, $removed));
    }
}
