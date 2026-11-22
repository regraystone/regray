<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * A product form's images, shared by the stone and furniture forms: the
 * thumbnail, and the gallery of its other photos, each row a photo already
 * saved (`id`, one of this product's) or a new one (`file`). A saved photo
 * left out is taken off. The limits are config/products.php's.
 */
trait ProductImageRules
{
    /** The product being changed, or null when one is being added. */
    abstract protected function product(): ?Model;

    /** Whether a new product must come with a thumbnail. */
    abstract protected function needsThumbnail(): bool;

    /** @return array<string, array<int, mixed>> */
    protected function imageRules(): array
    {
        $images = ['file', 'mimes:'.implode(',', config('products.image_mimes'))];
        $product = $this->product();
        /** @var list<int> $saved */
        $saved = $product !== null && method_exists($product, 'images') ? $product->images()->pluck('id')->all() : [];

        return [
            'thumbnail' => [$product === null && $this->needsThumbnail() ? 'required' : 'nullable', ...$images, 'max:'.config('products.thumbnail_max_kb')],
            'gallery' => ['nullable', 'array', 'max:'.config('products.gallery_max')],
            'gallery.*' => ['array:id,file'],
            'gallery.*.id' => ['nullable', 'integer', 'distinct', 'required_without:gallery.*.file', 'prohibits:gallery.*.file', Rule::in($saved)],
            'gallery.*.file' => ['nullable', 'required_without:gallery.*.id', ...$images, 'max:'.config('products.photo_max_kb')],
        ];
    }

    /** @return array<string, string> */
    protected function imageMessages(): array
    {
        return [
            'thumbnail.required' => __('Add the product\'s thumbnail.'),
            'thumbnail.mimes' => __('The thumbnail has to be a PNG, JPG or WebP.'),
            'thumbnail.max' => __('The thumbnail can be :max KB at most.'),
            'gallery.max' => __('A gallery can have :max photos at most.'),
            'gallery.*.id.in' => __('That photo is no longer in the gallery. Reload the page and try again.'),
            'gallery.*.id.required_without' => __('Add a photo, or remove the row.'),
            'gallery.*.file.required_without' => __('Add a photo, or remove the row.'),
            'gallery.*.file.mimes' => __('A photo has to be a PNG, JPG or WebP.'),
            'gallery.*.file.max' => __('A photo can be :max KB at most.'),
        ];
    }

    /** A gallery row's file arrives apart from its other fields; put each with its own row. */
    protected function mergeGallery(): void
    {
        $gallery = $this->input('gallery');
        /** @var array<int|string, mixed> $files */
        $files = is_array($this->file('gallery')) ? $this->file('gallery') : [];

        if (! is_array($gallery) && $files === []) {
            return;
        }

        $gallery = is_array($gallery) ? $gallery : [];
        $rows = [];

        foreach (array_keys($gallery + $files) as $key) {
            $row = is_array($gallery[$key] ?? null) ? $gallery[$key] : [];
            $file = is_array($files[$key] ?? null) ? ($files[$key]['file'] ?? null) : null;

            $rows[] = [
                ...array_intersect_key($row, array_flip(['id'])),
                ...($file === null ? [] : ['file' => $file]),
            ];
        }

        $this->merge(['gallery' => $rows]);
    }

    public function thumbnail(): ?UploadedFile
    {
        $file = $this->file('thumbnail');

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * The gallery in its order: each photo a saved one's id or a new file.
     *
     * @return list<array{id: int|null, file: UploadedFile|null}>
     */
    public function gallery(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->validated('gallery') ?? [];

        return array_map(fn (array $row): array => [
            'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
            'file' => ($row['file'] ?? null) instanceof UploadedFile ? $row['file'] : null,
        ], $rows);
    }
}
