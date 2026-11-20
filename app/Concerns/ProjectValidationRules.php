<?php

namespace App\Concerns;

use App\Models\Brand;
use App\Models\Project;
use App\Models\Stone;
use App\Support\ReservedSlugs;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Add and Edit project forms' rules, shared by StoreProjectRequest and
 * UpdateProjectRequest. The limits are the columns' (the projects
 * migrations) and config/projects.php's.
 *
 * A project's page is the public catch-all `/{slug}`, so its slug is none of
 * the names Regray keeps for its own pages (ReservedSlugs), and no stone's
 * either: that link finds a stone first. It is unique among every project,
 * archived ones included, so an archived project's link is never reused.
 *
 * The gallery is the photos in the order the page shows them: each row is a
 * photo already saved (`id`, one of this project's) or a new one (`file`),
 * A saved photo left out is taken off. A photo's alt text is its file's own
 * name (ProjectImage::label()), so the form asks for none.
 *
 * The brand, stones and categories must be ones Regray shows: an active
 * brand, active stones of brands not archived, active categories.
 *
 * Validation runs before anything touches storage, so a bad upload never
 * deletes or writes a file.
 */
trait ProjectValidationRules
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** The project being changed, or null when one is being added. */
    abstract protected function project(): ?Project;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $images = ['file', 'mimes:'.implode(',', config('projects.image_mimes'))];

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:100', 'regex:'.self::SLUG_PATTERN,
                Rule::notIn(ReservedSlugs::ALL),
                Rule::unique('projects', 'slug')->ignore($this->project()?->id),
                Rule::unique('stones', 'slug'),
            ],
            'location' => ['required', 'string', 'max:255'],
            'completed_year' => ['required', 'integer', 'between:1900,2100'],
            'overview' => ['required', 'string', 'max:'.config('projects.overview_max')],
            'brand_id' => [
                'nullable', 'integer',
                Rule::exists('brands', 'id')->where('is_active', true)->whereNull('archived_at'),
            ],
            'thumbnail' => [$this->project() === null ? 'required' : 'nullable', ...$images, 'max:'.config('projects.thumbnail_max_kb')],
            'gallery' => ['nullable', 'array', 'max:'.config('projects.gallery_max')],
            'gallery.*' => ['array:id,file'],
            'gallery.*.id' => [
                'nullable', 'integer', 'distinct', 'required_without:gallery.*.file', 'prohibits:gallery.*.file',
                Rule::in($this->project()?->images()->pluck('id')->all() ?? []),
            ],
            'gallery.*.file' => ['nullable', 'required_without:gallery.*.id', ...$images, 'max:'.config('projects.photo_max_kb')],
            'stones' => ['required', 'array', 'min:1'],
            'stones.*' => [
                'integer', 'distinct',
                Rule::exists('stones', 'id')->where(fn ($stones) => $stones
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereIn('brand_id', Brand::query()->notArchived()->select('id'))),
            ],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'distinct', Rule::exists('project_categories', 'id')->where('is_active', true)],
            'is_published' => ['required', 'boolean'],
        ];
    }

    /**
     * Trim what was typed, lower-case the slug, read an empty brand as none,
     * and number the gallery, stones and categories from 0.
     */
    protected function prepareForValidation(): void
    {
        $text = fn (mixed $value): mixed => is_string($value) ? trim($value) : $value;
        $list = fn (mixed $value): mixed => is_array($value) ? array_values($value) : $value;
        $gallery = $this->input('gallery');
        /** @var array<int|string, mixed> $files */
        $files = is_array($this->file('gallery')) ? $this->file('gallery') : [];

        $this->merge([
            'name' => $text($this->input('name')),
            'slug' => is_string($this->input('slug')) ? mb_strtolower(trim($this->input('slug'))) : $this->input('slug'),
            'location' => $text($this->input('location')),
            'overview' => $text($this->input('overview')),
            'brand_id' => in_array($this->input('brand_id'), ['', null], true) ? null : $this->input('brand_id'),
            'stones' => $list($this->input('stones')),
            'categories' => $list($this->input('categories')),
        ]);

        // A row's file arrives apart from its other fields; put each with its own row.
        if (is_array($gallery) || $files !== []) {
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
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('Enter the project\'s name.'),
            'slug.required' => __('Enter the slug for its link.'),
            'slug.regex' => __('Use lower-case letters, numbers and single hyphens, such as ginza-one.'),
            'slug.not_in' => __('That slug is kept for one of Regray\'s own pages. Choose another.'),
            'slug.unique' => __('Another project already uses that slug.'),
            'location.required' => __('Enter where the project is.'),
            'completed_year.required' => __('Enter the year it was completed.'),
            'completed_year.integer' => __('Enter a year, such as 2026.'),
            'completed_year.between' => __('Enter a year from 1900 to 2100.'),
            'overview.required' => __('Write a short overview of the project.'),
            'overview.max' => __('Keep the overview to :max characters.'),
            'brand_id.exists' => __('That brand is no longer on Regray. Choose another, or none.'),
            'thumbnail.required' => __('Add the project\'s thumbnail.'),
            'thumbnail.mimes' => __('The thumbnail has to be a PNG, JPG or WebP.'),
            'thumbnail.max' => __('The thumbnail can be :max KB at most.'),
            'gallery.max' => __('A gallery can have :max photos at most.'),
            'gallery.*.id.in' => __('That photo is no longer in the gallery. Reload the page and try again.'),
            'gallery.*.id.required_without' => __('Add a photo, or remove the row.'),
            'gallery.*.file.required_without' => __('Add a photo, or remove the row.'),
            'gallery.*.file.mimes' => __('A photo has to be a PNG, JPG or WebP.'),
            'gallery.*.file.max' => __('A photo can be :max KB at most.'),
            'stones.required' => __('Choose at least one stone used in the project.'),
            'stones.min' => __('Choose at least one stone used in the project.'),
            'stones.*.exists' => __('That stone is no longer in the catalogue. Remove it.'),
            'stones.*.distinct' => __('That stone is already chosen.'),
            'categories.*.exists' => __('That category is no longer offered. Remove it.'),
            'categories.*.distinct' => __('That category is already chosen.'),
        ];
    }

    /**
     * Say why the slug is taken when it is an archived project's, or a
     * stone's.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('slug')) {
                    return;
                }

                $slug = $this->input('slug');
                $message = match (true) {
                    Stone::query()->withTrashed()->where('slug', $slug)->exists() => __('A stone already uses that slug, and its link would open the stone. Choose another.'),
                    Project::query()->onlyTrashed()->where('slug', $slug)->whereKeyNot($this->project()->id ?? 0)->exists() => __('That slug belonged to an archived project and can\'t be used again.'),
                    default => null,
                };

                if (is_string($message)) {
                    $validator->errors()->forget('slug');
                    $validator->errors()->add('slug', $message);
                }
            },
        ];
    }

    /**
     * The project's own fields and its stones and categories, in the shape
     * the service writes. The images are read on their own (thumbnail(),
     * gallery()).
     *
     * @return array{name: string, slug: string, location: string, completed_year: int, overview: string, brand_id: int|null, is_published: bool, stones: list<int>, categories: list<int>}
     */
    public function projectData(): array
    {
        $ids = fn (string $key): array => array_values(array_map(fn (mixed $id): int => (int) $id, $this->validated($key) ?? []));
        $brand = $this->validated('brand_id');

        return [
            'name' => (string) $this->validated('name'),
            'slug' => (string) $this->validated('slug'),
            'location' => (string) $this->validated('location'),
            'completed_year' => (int) $this->validated('completed_year'),
            'overview' => (string) $this->validated('overview'),
            'brand_id' => $brand === null ? null : (int) $brand,
            'is_published' => $this->boolean('is_published'),
            'stones' => $ids('stones'),
            'categories' => $ids('categories'),
        ];
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
