<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\Fulfillment;
use App\Models\Service;
use App\Models\ServiceFeature;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Support\CatalogLocks;
use App\Support\Slugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The All services page's writes: adding, changing, showing or hiding, and
 * archiving a service.
 * Service types and questions are lists of their own (ServiceTypeList,
 * QuestionList). The Inquire page reads them through
 * InquiryCatalogRepositoryInterface; this is the admin's side.
 *
 * A service's "what it includes" lines (service_features), its stone types
 * (service_stone_type) and the questions it asks (service_detail_fields, in
 * order, each required or not) are replaced whole by the form's, in the same
 * transaction as the service.
 *
 * Its questions are chosen from the Questions page's list (QuestionList),
 * where new ones are written.
 *
 * Slugs are made from the name when a service is added, unique among every
 * one, archived included, and never change after: code reaches some by slug
 * (FurnitureCustomizationService, the Inquire page's furniture type).
 *
 * Archiving sets `archived_at` and turns `is_active` off, so everything that
 * offers services leaves it out without asking about archiving. The row
 * stays, as sent inquiries reference it.
 */
class ServiceAdminService
{
    private const SLUG_MAX = 100;

    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    /**
     * @param  array{name: string, service_type_id: int, description: string, requires_stones: bool, fulfillment: Fulfillment, delivery_note: string|null, schedule_note: string|null, lead_days: int|null, is_application: bool, has_applications: bool, is_active: bool, sort_order: int, features: list<string>, stone_type_ids: list<int>, questions: list<array{id: int, required: bool}>}  $data
     */
    public function create(array $data, User $by, ?string $ipAddress, ?string $userAgent): Service
    {
        return DB::transaction(function () use ($data, $by, $ipAddress, $userAgent): Service {
            self::holdType($data['service_type_id']);
            $service = new Service(self::columns($data));
            $service->slug = Slugs::unique(Service::query(), $data['name'], self::SLUG_MAX);
            $service->save();
            $this->replaceDetails($service, $data);
            $this->audit($by, AuditEvent::Created, $service, null, self::snapshot($service), $ipAddress, $userAgent);

            return $service;
        });
    }

    /**
     * @param  array{name: string, service_type_id: int, description: string, requires_stones: bool, fulfillment: Fulfillment, delivery_note: string|null, schedule_note: string|null, lead_days: int|null, is_application: bool, has_applications: bool, is_active: bool, sort_order: int, features: list<string>, stone_type_ids: list<int>, questions: list<array{id: int, required: bool}>}  $data
     */
    public function update(Service $service, array $data, User $by, ?string $ipAddress, ?string $userAgent): Service
    {
        return DB::transaction(function () use ($service, $data, $by, $ipAddress, $userAgent): Service {
            $before = self::snapshot($service);
            self::holdType($data['service_type_id']);
            $service->fill(self::columns($data))->save();
            $this->replaceDetails($service, $data);
            $this->audit($by, AuditEvent::Updated, $service, $before, self::snapshot($service->refresh()), $ipAddress, $userAgent);

            return $service;
        });
    }

    /** Show a service on the Inquire page (its type and stones allowing), or hide it. */
    public function setActive(Service $service, bool $active, User $by, ?string $ipAddress, ?string $userAgent): Service
    {
        if ($service->is_active === $active) {
            return $service;
        }

        DB::transaction(function () use ($service, $active, $by, $ipAddress, $userAgent): void {
            $before = self::snapshot($service);
            $service->forceFill(['is_active' => $active])->save();
            $this->audit($by, AuditEvent::StatusChanged, $service, $before, self::snapshot($service), $ipAddress, $userAgent);
        });

        return $service;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function columns(array $data): array
    {
        return array_diff_key($data, array_flip(['features', 'stone_type_ids', 'questions']));
    }

    /**
     * @param  array{features: list<string>, stone_type_ids: list<int>, questions: list<array{id: int, required: bool}>}  $data
     */
    private function replaceDetails(Service $service, array $data): void
    {
        ServiceFeature::query()->where('service_id', $service->id)->delete();
        ServiceFeature::query()->insert(array_map(
            fn (string $text, int $index): array => ['service_id' => $service->id, 'sort_order' => $index, 'text' => $text],
            $data['features'],
            array_keys($data['features']),
        ));

        // Held, so none can be archived on the Stone types page under the save (CatalogLocks).
        CatalogLocks::hold('stone_types', $data['stone_type_ids'], 'stone_type_ids', __('One of those stone types is no longer offered. Remove it.'));
        $service->stoneTypes()->sync($data['stone_type_ids']);

        // Held, so none can be archived on the Questions page under the save.
        CatalogLocks::hold('detail_fields', array_column($data['questions'], 'id'), 'questions', __('One of those questions is no longer listed. Remove it.'), 'archived_at');
        $questions = [];

        foreach ($data['questions'] as $index => $question) {
            $questions[$question['id']] = ['sort_order' => $index, 'is_required' => $question['required']];
        }

        $service->detailFields()->sync($questions);
        $service->unsetRelation('features')->unsetRelation('stoneTypes')->unsetRelation('detailFields');
    }

    /**
     * Hold the service's type, so it can't be archived on the Service types
     * page under the save (CatalogLocks).
     */
    private static function holdType(int $typeId): void
    {
        CatalogLocks::hold('service_types', [$typeId], 'service_type_id', __('That service type is no longer listed. Choose another.'), 'archived_at');
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
            'is_application' => $service->is_application,
            'has_applications' => $service->has_applications,
            'fulfillment' => $service->fulfillment->value,
            'delivery_note' => $service->delivery_note,
            'schedule_note' => $service->schedule_note,
            'lead_days' => $service->lead_days,
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
}
