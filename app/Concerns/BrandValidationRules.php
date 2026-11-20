<?php

namespace App\Concerns;

use App\Enums\SocialPlatform;
use App\Models\Brand;
use App\Support\ReservedSlugs;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Add and Edit brand forms' rules, shared by StoreBrandRequest and
 * UpdateBrandRequest. The limits are the columns' (the brands migrations) and
 * config/brands.php's.
 *
 * The slug is unique among every brand, archived ones included: an archived
 * brand keeps its slug, so it is never free for a new one. Nor may it be one
 * of the names Regray keeps for its own pages (ReservedSlugs).
 *
 * A social link is one per platform, and only on that platform's own site
 * (SocialPlatform::owns()), so its icon is never on someone else's link.
 *
 * Validation runs before anything touches storage, so a bad upload never
 * deletes or writes a file.
 */
trait BrandValidationRules
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** The brand being changed, or null when one is being added. */
    abstract protected function brand(): ?Brand;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $images = ['file', 'mimes:'.implode(',', config('brands.image_mimes'))];

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:100', 'regex:'.self::SLUG_PATTERN,
                Rule::notIn(ReservedSlugs::ALL),
                Rule::unique('brands', 'slug')->ignore($this->brand()?->id),
            ],
            'tagline' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'website_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'logo' => [$this->brand() === null ? 'required' : 'nullable', ...$images, 'max:'.config('brands.logo_max_kb')],
            'thumbnail' => ['nullable', ...$images, 'max:'.config('brands.thumbnail_max_kb')],
            'remove_thumbnail' => ['sometimes', 'boolean'],
            'facts' => ['nullable', 'array', 'max:'.config('brands.facts_max')],
            'facts.*' => ['array:label,value'],
            'facts.*.label' => ['required', 'string', 'max:'.config('brands.fact_label_max')],
            'facts.*.value' => ['required', 'string', 'max:'.config('brands.fact_value_max')],
            'makes' => ['nullable', 'array', 'max:'.config('brands.makes_max')],
            'makes.*' => ['required', 'string', 'max:'.config('brands.make_max'), 'distinct:ignore_case'],
            'socials' => ['nullable', 'array', 'max:'.count(SocialPlatform::cases())],
            'socials.*' => ['array:platform,url'],
            'socials.*.platform' => ['required', Rule::enum(SocialPlatform::class), 'distinct'],
            'socials.*.url' => ['required', 'string', 'max:255', 'url:http,https'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:32767'],
        ];
    }

    /**
     * Trim what was typed, lower-case the slug, give a bare website or social
     * link its `https://`, and number the facts, tags and links from 0.
     */
    protected function prepareForValidation(): void
    {
        $text = fn (mixed $value): mixed => is_string($value) ? trim($value) : $value;

        $address = function (mixed $value) use ($text): mixed {
            $value = $text($value);

            return is_string($value) && $value !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)
                ? 'https://'.$value
                : $value;
        };

        $website = $address($this->input('website_url'));
        $socials = $this->input('socials');

        $facts = $this->input('facts');
        $makes = $this->input('makes');

        $this->merge([
            'name' => $text($this->input('name')),
            'slug' => is_string($this->input('slug')) ? mb_strtolower(trim($this->input('slug'))) : $this->input('slug'),
            'tagline' => $text($this->input('tagline')),
            'description' => $text($this->input('description')),
            'website_url' => $website === '' ? null : $website,
            'facts' => is_array($facts) ? array_values(array_map(
                fn (mixed $fact): mixed => is_array($fact) ? array_map($text, $fact) : $fact,
                $facts,
            )) : $facts,
            'makes' => is_array($makes) ? array_values(array_map($text, $makes)) : $makes,
            'socials' => is_array($socials) ? array_values(array_map(
                fn (mixed $social): mixed => is_array($social)
                    ? [...$social, 'platform' => $text($social['platform'] ?? null), 'url' => $address($social['url'] ?? null)]
                    : $social,
                $socials,
            )) : $socials,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('Enter the brand\'s name.'),
            'slug.required' => __('Enter the slug for its link.'),
            'slug.regex' => __('Use lower-case letters, numbers and single hyphens, such as cucine-lube.'),
            'slug.not_in' => __('That slug is kept for one of Regray\'s own pages. Choose another.'),
            'slug.unique' => __('Another brand already uses that slug.'),
            'website_url.url' => __('Enter a full web address, such as https://www.cucinelube.it.'),
            'logo.required' => __('Add the brand\'s logo.'),
            'logo.mimes' => __('The logo has to be a PNG, JPG, WebP or SVG.'),
            'logo.max' => __('The logo can be :max KB at most.'),
            'thumbnail.mimes' => __('The thumbnail has to be a PNG, JPG, WebP or SVG.'),
            'thumbnail.max' => __('The thumbnail can be :max KB at most.'),
            'facts.max' => __('A brand can have :max facts at most.'),
            'facts.*.label.required' => __('Give this fact a label, or remove it.'),
            'facts.*.value.required' => __('Give this fact a value, or remove it.'),
            'facts.*.label.max' => __('A fact\'s label can be :max characters at most.'),
            'facts.*.value.max' => __('A fact\'s value can be :max characters at most.'),
            'makes.max' => __('A brand can list :max things it makes at most.'),
            'makes.*.required' => __('Remove the empty tag.'),
            'makes.*.max' => __('Each tag can be :max characters at most.'),
            'makes.*.distinct' => __('That tag is already listed.'),
            'socials.max' => __('A brand can have one link for each network.'),
            'socials.*.platform.required' => __('Choose the network.'),
            'socials.*.platform.enum' => __('Choose Facebook, Twitter, X, Instagram or TikTok.'),
            'socials.*.platform.distinct' => __('That network already has a link.'),
            'socials.*.url.required' => __('Enter the link, or remove it.'),
            'socials.*.url.url' => __('Enter a full web address, such as https://www.instagram.com/regraystoneph.'),
            'socials.*.url.max' => __('Use :max characters or fewer.'),
            'sort_order.integer' => __('Enter a whole number.'),
            'sort_order.min' => __('Enter 0 or more.'),
        ];
    }

    /**
     * Say so when the slug is an archived brand's, and refuse a social link
     * that is not on its network's own site.
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

                $archived = Brand::query()
                    ->where('slug', $this->input('slug'))
                    ->whereNotNull('archived_at')
                    ->whereKeyNot($this->brand()->id ?? 0)
                    ->exists();

                if ($archived) {
                    $validator->errors()->forget('slug');
                    $validator->errors()->add('slug', __('That slug belonged to an archived brand and can\'t be used again.'));
                }
            },
            function (Validator $validator): void {
                $socials = $this->input('socials');

                foreach (is_array($socials) ? $socials : [] as $index => $social) {
                    $platform = SocialPlatform::tryFrom((string) ($social['platform'] ?? ''));
                    $url = $social['url'] ?? null;

                    if ($platform === null || ! is_string($url) || $validator->errors()->has("socials.{$index}.url") || $platform->owns($url)) {
                        continue;
                    }

                    $validator->errors()->add("socials.{$index}.url", __('That link isn\'t on :network. Use one on :site.', [
                        'network' => $platform->label(),
                        'site' => $platform->hosts()[0],
                    ]));
                }
            },
        ];
    }

    /**
     * The brand's own fields, in the shape the service writes. The images are
     * read on their own (logo(), thumbnail(), removesThumbnail()).
     *
     * @return array{name: string, slug: string, tagline: string|null, description: string|null, website_url: string|null, is_active: bool, sort_order: int, facts: list<array{label: string, value: string}>, makes: list<string>, socials: list<array{platform: string, url: string}>}
     */
    public function brandData(): array
    {
        $nullable = fn (string $key): ?string => ($value = $this->validated($key)) === null || $value === '' ? null : (string) $value;

        /** @var list<array{label: string, value: string}> $facts */
        $facts = array_values(array_map(
            fn (array $fact): array => ['label' => (string) $fact['label'], 'value' => (string) $fact['value']],
            $this->validated('facts') ?? [],
        ));

        return [
            'name' => (string) $this->validated('name'),
            'slug' => (string) $this->validated('slug'),
            'tagline' => $nullable('tagline'),
            'description' => $nullable('description'),
            'website_url' => $nullable('website_url'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->validated('sort_order'),
            'facts' => $facts,
            'makes' => array_values(array_map(fn (mixed $make): string => (string) $make, $this->validated('makes') ?? [])),
            'socials' => array_values(array_map(
                fn (array $social): array => ['platform' => (string) $social['platform'], 'url' => (string) $social['url']],
                $this->validated('socials') ?? [],
            )),
        ];
    }

    public function logo(): ?UploadedFile
    {
        $file = $this->file('logo');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function thumbnail(): ?UploadedFile
    {
        $file = $this->file('thumbnail');

        return $file instanceof UploadedFile ? $file : null;
    }

    /** Whether the form asked to take the thumbnail off, with no new one to put on. */
    public function removesThumbnail(): bool
    {
        return $this->boolean('remove_thumbnail') && $this->thumbnail() === null;
    }
}
