<?php

namespace Database\Seeders;

use App\Enums\Fulfillment;
use App\Models\DetailField;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The services of data/services.php, each with how it reaches the client
 * (`fulfillment`), the lines under its delivery method and date and the
 * fewest days ahead it can be asked for, its features and the questions it asks (`service_detail_fields`). Run again, it updates them in place and
 * replaces their features and questions. A service no longer listed is made
 * inactive rather than deleted, since sent inquiries reference it.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // A service needs its type and its questions, so this still works when it is run alone.
        $this->call([ServiceTypeSeeder::class, DetailFieldSeeder::class]);

        /** @var list<array{slug: string, services: list<array{slug: string, name: string, description: string, requires_stones: bool, fulfillment: string, delivery_note?: string, schedule_note?: string, lead_days?: int, features: list<string>, fields: array<string, bool>}>}> $types */
        $types = require database_path('seeders/data/services.php');
        $fieldIds = DetailField::query()->pluck('id', 'slug');
        $listed = [];

        foreach ($types as $type) {
            $typeId = ServiceType::query()->where('slug', $type['slug'])->value('id');

            foreach ($type['services'] as $sort => $data) {
                $service = Service::query()->updateOrCreate(['slug' => $data['slug']], [
                    'service_type_id' => $typeId,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'requires_stones' => $data['requires_stones'],
                    'fulfillment' => Fulfillment::from($data['fulfillment']),
                    'delivery_note' => $data['delivery_note'] ?? null,
                    'schedule_note' => $data['schedule_note'] ?? null,
                    'lead_days' => $data['lead_days'] ?? null,
                    'sort_order' => $sort,
                    'is_active' => true,
                ]);

                $service->features()->delete();
                $service->features()->createMany(array_map(
                    fn (string $text, int $position): array => ['sort_order' => $position, 'text' => $text],
                    $data['features'],
                    array_keys($data['features']),
                ));

                $fields = [];

                foreach (array_keys($data['fields']) as $position => $slug) {
                    $fields[$fieldIds[$slug] ?? throw new RuntimeException("Service {$data['slug']} asks the unknown question {$slug}.")] = [
                        'sort_order' => $position,
                        'is_required' => $data['fields'][$slug],
                    ];
                }

                $service->detailFields()->sync($fields);
                $listed[] = $data['slug'];
            }
        }

        Service::query()->whereNotIn('slug', $listed)->update(['is_active' => false]);

        // The choices offered for only some services, now that they exist.
        app(ChoiceSeeder::class)->linkServices();
    }
}
