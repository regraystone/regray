<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use App\Enums\Fulfillment;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\Service;
use App\Models\ServiceFeature;
use App\Models\ServiceType;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The admin's Services page writes: adding, changing and archiving a service
 * and a service type. The Inquire page reads them through
 * InquiryCatalogRepositoryInterface; this is the admin's side.
 *
 * A service's "what it includes" lines (service_features), its stone types
 * (service_stone_type) and the questions it asks (service_detail_fields, in
 * order, each required or not) are replaced whole by the form's, in the same
 * transaction as the service.
 *
 * A question written on the form rather than chosen is added to the catalog
 * first (detail_fields, and its choices as closed detail_field_options), as a
 * project question, answered once for the inquiry: stone questions are
 * columns of their own and stay the seeded five. Other services can then
 * choose it too.
 *
 * Slugs are made from the name when a service or type is added, unique among
 * every one, archived included, and never change after: code reaches some
 * by slug (FurnitureCustomizationService, the Inquire page's furniture type).
 *
 * Archiving sets `archived_at` and turns `is_active` off, so everything that
 * offers services leaves it out without asking about archiving. The row
 * stays, as sent inquiries reference it.
 */
class ServiceAdminService
{
    private const SLUG_MAX = 100;

    /** detail_fields.slug's length. */
    private const QUESTION_SLUG_MAX = 50;

    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    /**
     * @param  array{name: string, service_type_id: int, description: string, requires_stones: bool, fulfillment: Fulfillment, is_active: bool, sort_order: int, features: list<string>, stone_type_ids: list<int>, questions: list<array{id: int|null, name: string|null, answer_type: DetailAnswerType|null, choices: list<string>, required: bool}>}  $data
     */
    public function create(array $data, User $by, ?string $ipAddress, ?string $userAgent): Service
    {
        return DB::transaction(function () use ($data, $by, $ipAddress, $userAgent): Service {
            $service = new Service(self::columns($data));
            $service->slug = self::uniqueSlug(Service::query(), $data['name']);
            $service->save();
            $this->replaceDetails($service, $data, $by, $ipAddress, $userAgent);
            $this->audit($by, AuditEvent::Created, $service, null, self::snapshot($service), $ipAddress, $userAgent);

            return $service;
        });
    }

    /**
     * @param  array{name: string, service_type_id: int, description: string, requires_stones: bool, fulfillment: Fulfillment, is_active: bool, sort_order: int, features: list<string>, stone_type_ids: list<int>, questions: list<array{id: int|null, name: string|null, answer_type: DetailAnswerType|null, choices: list<string>, required: bool}>}  $data
     */
    public function update(Service $service, array $data, User $by, ?string $ipAddress, ?string $userAgent): Service
    {
        return DB::transaction(function () use ($service, $data, $by, $ipAddress, $userAgent): Service {
            $before = self::snapshot($service);
            $service->fill(self::columns($data))->save();
            $this->replaceDetails($service, $data, $by, $ipAddress, $userAgent);
            $this->audit($by, AuditEvent::Updated, $service, $before, self::snapshot($service->refresh()), $ipAddress, $userAgent);

            return $service;
        });
    }

    /** Archive a service: it leaves the page and is no longer offered. */
    public function archive(Service $service, User $by, ?string $ipAddress, ?string $userAgent): Service
    {
        DB::transaction(function () use ($service, $by, $ipAddress, $userAgent): void {
            $before = self::snapshot($service);
            $service->forceFill(['is_active' => false, 'archived_at' => now()])->save();
            $this->audit($by, AuditEvent::Archived, $service, $before, self::snapshot($service), $ipAddress, $userAgent);
        });

        return $service;
    }

    /** @param  array{name: string, is_active: bool, sort_order: int}  $data */
    public function createType(array $data, User $by, ?string $ipAddress, ?string $userAgent): ServiceType
    {
        return DB::transaction(function () use ($data, $by, $ipAddress, $userAgent): ServiceType {
            $type = new ServiceType($data);
            $type->slug = self::uniqueSlug(ServiceType::query(), $data['name']);
            $type->save();
            $this->audit($by, AuditEvent::Created, $type, null, self::typeSnapshot($type), $ipAddress, $userAgent);

            return $type;
        });
    }

    /** @param  array{name: string, is_active: bool, sort_order: int}  $data */
    public function updateType(ServiceType $type, array $data, User $by, ?string $ipAddress, ?string $userAgent): ServiceType
    {
        return DB::transaction(function () use ($type, $data, $by, $ipAddress, $userAgent): ServiceType {
            $before = self::typeSnapshot($type);
            $type->fill($data)->save();
            $this->audit($by, AuditEvent::Updated, $type, $before, self::typeSnapshot($type), $ipAddress, $userAgent);

            return $type;
        });
    }

    /**
     * Archive a service type, once none of its services is left on the page:
     * they are moved to another type or archived first, so archiving a type
     * never takes a service with it unseen.
     *
     * @throws ValidationException when it still has services
     */
    public function archiveType(ServiceType $type, User $by, ?string $ipAddress, ?string $userAgent): ServiceType
    {
        return DB::transaction(function () use ($type, $by, $ipAddress, $userAgent): ServiceType {
            $locked = ServiceType::query()->lockForUpdate()->findOrFail($type->id);
            $left = $locked->services()->notArchived()->count();

            if ($left > 0) {
                throw ValidationException::withMessages([
                    'archive' => trans_choice('Move or archive its service first.|Move or archive its :count services first.', $left, ['count' => $left]),
                ]);
            }

            $before = self::typeSnapshot($locked);
            $locked->forceFill(['is_active' => false, 'archived_at' => now()])->save();
            $this->audit($by, AuditEvent::Archived, $locked, $before, self::typeSnapshot($locked), $ipAddress, $userAgent);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function columns(array $data): array
    {
        return array_diff_key($data, array_flip(['features', 'stone_type_ids', 'questions']));
    }

    /**
     * @param  array{features: list<string>, stone_type_ids: list<int>, questions: list<array{id: int|null, name: string|null, answer_type: DetailAnswerType|null, choices: list<string>, required: bool}>}  $data
     */
    private function replaceDetails(Service $service, array $data, User $by, ?string $ipAddress, ?string $userAgent): void
    {
        ServiceFeature::query()->where('service_id', $service->id)->delete();
        ServiceFeature::query()->insert(array_map(
            fn (string $text, int $index): array => ['service_id' => $service->id, 'sort_order' => $index, 'text' => $text],
            $data['features'],
            array_keys($data['features']),
        ));

        $service->stoneTypes()->sync($data['stone_type_ids']);

        $questions = [];

        foreach ($data['questions'] as $index => $question) {
            $id = $question['id'] ?? $this->createQuestion((string) $question['name'], $question['answer_type'] ?? DetailAnswerType::Text, $question['choices'], $by, $ipAddress, $userAgent)->id;
            $questions[$id] = ['sort_order' => $index, 'is_required' => $question['required']];
        }

        $service->detailFields()->sync($questions);
        $service->unsetRelation('features')->unsetRelation('stoneTypes')->unsetRelation('detailFields');
    }

    /**
     * Add a question to the catalog, after the seeded ones. A written or
     * number answer is free, so it takes what the client types; a list's
     * choices are the only answers it takes.
     *
     * @param  list<string>  $choices
     */
    private function createQuestion(string $name, DetailAnswerType $type, array $choices, User $by, ?string $ipAddress, ?string $userAgent): DetailField
    {
        $field = DetailField::query()->create([
            'slug' => self::uniqueSlug(DetailField::query(), $name, self::QUESTION_SLUG_MAX, '_'),
            'name' => $name,
            'scope' => DetailFieldScope::Project,
            'answer_type' => $type,
            'allows_custom' => in_array($type, [DetailAnswerType::Text, DetailAnswerType::Number], true),
            'sort_order' => ((int) DetailField::query()->max('sort_order')) + 1,
        ]);

        $slugs = [];

        foreach ($choices as $index => $choice) {
            $base = Str::limit(Str::slug($choice), 96, '') ?: 'choice';
            $slug = $base;

            for ($number = 2; in_array($slug, $slugs, true); $number++) {
                $slug = "{$base}-{$number}";
            }

            $slugs[] = $slug;

            DetailFieldOption::query()->create([
                'detail_field_id' => $field->id,
                'slug' => $slug,
                'name' => $choice,
                'sort_order' => $index,
                'is_active' => true,
                'is_suggestion' => false,
                'links_to_stone' => false,
            ]);
        }

        $this->audit($by, AuditEvent::Created, $field, null, [
            'name' => $field->name,
            'slug' => $field->slug,
            'scope' => $field->scope->value,
            'answer_type' => $field->answer_type->value,
            'choices' => $choices,
        ], $ipAddress, $userAgent);

        return $field;
    }

    /**
     * The name as a slug, numbered when another row has it already.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function uniqueSlug(Builder $query, string $name, int $max = self::SLUG_MAX, string $separator = '-'): string
    {
        $base = Str::limit(Str::slug($name, $separator), $max - 4, '') ?: 'item';
        $taken = (clone $query)->where('slug', 'like', addcslashes($base, '\\%_').'%')->pluck('slug')->all();

        $slug = $base;

        for ($number = 2; in_array($slug, $taken, true); $number++) {
            $slug = "{$base}{$separator}{$number}";
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(User $by, AuditEvent $event, Model $subject, ?array $before, ?array $after, ?string $ipAddress, ?string $userAgent): void
    {
        $this->auditLogs->record($by->id, $event, $subject, $before, $after, $ipAddress, $userAgent);
    }

    /**
     * What an audit row records of a service.
     *
     * @return array<string, mixed>
     */
    private static function snapshot(Service $service): array
    {
        $service->loadMissing(['features', 'stoneTypes:stone_types.id', 'detailFields:detail_fields.id']);

        return [
            'name' => $service->name,
            'slug' => $service->slug,
            'service_type_id' => $service->service_type_id,
            'description' => $service->description,
            'requires_stones' => $service->requires_stones,
            'fulfillment' => $service->fulfillment->value,
            'features' => $service->features->pluck('text')->all(),
            'stone_type_ids' => $service->stoneTypes->pluck('id')->sort()->values()->all(),
            'questions' => $service->detailFields->map(fn ($field): array => [
                'id' => $field->id,
                'required' => $field->isRequiredByService(),
            ])->all(),
            'is_active' => $service->is_active,
            'sort_order' => $service->sort_order,
            'archived_at' => $service->archived_at?->toIso8601String(),
        ];
    }

    /**
     * What an audit row records of a service type.
     *
     * @return array<string, mixed>
     */
    private static function typeSnapshot(ServiceType $type): array
    {
        return [
            'name' => $type->name,
            'slug' => $type->slug,
            'is_active' => $type->is_active,
            'sort_order' => $type->sort_order,
            'archived_at' => $type->archived_at?->toIso8601String(),
        ];
    }
}
