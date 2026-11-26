<?php

namespace App\Http\Requests\Inquiry;

use App\Data\FurnitureSize;
use App\Data\PhAddress;
use App\Data\SizeMeasurement;
use App\Data\ThicknessMeasurement;
use App\Enums\DeliveryMethod;
use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use App\Http\Requests\Concerns\ValidatesPhAddress;
use App\Models\Brand;
use App\Models\ClientAddress;
use App\Models\DetailField;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Service;
use App\Models\Stone;
use App\Rules\MeasurementRule;
use App\Services\ScheduleService;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Mirrors `inquirySchema` in resources/js/lib/inquiry-schema.ts, field for
 * field: the keys are the form's own (`serviceId`, `stones.*.stoneId`,
 * `neededBy`), so an error at `projects.0.stones.1.quantity` is the same path
 * the page already knows how to point at.
 *
 * What else a project is asked depends on its service
 * (`service_detail_fields`), so those rules are built per project from the
 * service it names: a stone row answers only the stone-scope questions its
 * service lists (`quantity`, `slabSize`, `thickness`, `edge`, `finish`) and
 * must leave out the rest (only the slab services ask how many pieces, a slab
 * size and a thickness),
 * and the project-scope answers arrive as `answers.{slug}`, each checked
 * against its answer type, whether it is required and, for a closed list, its
 * options. A suggestion is never checked: a free entry is accepted only where
 * the question allows one, and then only by its type. A slab size or
 * thickness is a measurement ("160 x 320 cm", "20 mm", MeasurementRule): any
 * well-formed one stands, not only the stone's own options, since Other takes
 * any. A service that asks for
 * no stones (Installation Only) takes none, and no Help me choose either. The
 * messages are worded from the question's name, as the page words them.
 *
 * Every project names the brands the client would like (`brands`, by slug) or
 * No preference (`brandNoPreference`), one or the other. Which brands are
 * named never limits the service.
 *
 * Where the work goes follows its service's `fulfillment`, read from the
 * service and never from the request: `delivery_or_pickup` asks how they
 * would like to get it (`deliveryMethod`), with an address for a delivery and
 * none for a pickup; `installed` asks no method and needs the installation
 * address. The address comes one of two ways (addressSource()): a client
 * picks one of their own saved addresses (`addressId`), and staff enter one
 * with the address picker (`address`, PSGC codes, ValidatesPhAddress). The
 * inquiry keeps a copy of it either way.
 *
 * @phpstan-import-type ProjectInput from \App\Services\InquiryService
 */
class StoreInquiryRequest extends FormRequest
{
    use ValidatesPhAddress;

    /** Where a project's address comes from: the client's address book, or the address picker. */
    public const ADDRESS_FROM_BOOK = 'book';

    public const ADDRESS_FROM_PICKER = 'picker';

    /** The most brands one project may name: every active brand, with room to spare. */
    private const BRANDS_MAX = 50;

    /** The largest number an answer's `number_value` holds (decimal 12,2). */
    private const NUMBER_MAX = '9999999999.99';

    /** The longest free text an answer's `text_value` holds. */
    private const TEXT_MAX = 255;

    /** How far ahead a project's date may be, in years: further is taken for a typo. */
    public const DEADLINE_YEARS_MAX = 2;

    /** @var Collection<string, Service>|null */
    private ?Collection $services = null;

    /** @var Collection<int, DetailField>|null */
    private ?Collection $questions = null;

    /** @var array<int, PhAddress> Each project's address, once checked, by index. */
    private array $projectAddresses = [];

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $limits = config('inquiry.limits');
        $rules = [
            'projects' => ['required', 'array', 'min:1', 'max:'.$limits['projects']],
            'projects.*.name' => ['required', 'string', 'max:'.$limits['name']],

            // Whether it is still offered is after()'s, since an edit may keep its own.
            'projects.*.serviceId' => ['required', 'string', Rule::exists('services', 'slug')],

            'projects.*.stoneAdvice' => ['nullable', 'boolean'],
            'projects.*.stones' => ['nullable', 'array', 'max:'.$limits['stone_rows']],
            // An edit may keep a stone it already has, no longer shown or not (after() says when).
            'projects.*.stones.*.stoneId' => [
                'required', 'string',
                Rule::exists('stones', 'slug')->where(fn ($stones) => $stones
                    ->where(fn ($shown) => $shown->where('is_active', true)->whereNull('deleted_at')
                        ->whereIn('brand_id', Brand::query()->notArchived()->select('id')))
                    ->orWhereIn('slug', $this->heldStoneSlugs())),
            ],
            'projects.*.answers' => ['nullable', 'array'],
            // A custom choice's description, by question slug (checkCustomAnswers()).
            'projects.*.customAnswers' => ['nullable', 'array'],
            'projects.*.customAnswers.*' => ['nullable', 'string'],

            'projects.*.brands' => ['nullable', 'array', 'max:'.self::BRANDS_MAX],
            'projects.*.brands.*' => ['string', 'distinct', Rule::exists('brands', 'slug')->where(fn ($brands) => $brands
                ->where(fn ($shown) => $shown->where('is_active', true)->whereNull('archived_at'))
                ->orWhereIn('slug', $this->heldBrandSlugs()))],
            'projects.*.brandNoPreference' => ['nullable', 'boolean'],

            // Whether it is asked, and the address with it, is the service's: see after().
            'projects.*.deliveryMethod' => ['nullable', Rule::enum(DeliveryMethod::class)],
            ...$this->addressSource() === self::ADDRESS_FROM_BOOK
                ? [
                    'projects.*.addressId' => ['nullable', 'integer'],
                    'projects.*.keepAddress' => ['nullable', 'boolean'],
                    'projects.*.address' => ['prohibited'],
                ]
                : [
                    ...$this->phAddressRules('projects.*.address', required: false),
                    'projects.*.addressId' => ['prohibited'],
                    'projects.*.keepAddress' => ['prohibited'],
                ],
            'projects.*.neededBy' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now(ScheduleService::timezone())->addYears(self::DEADLINE_YEARS_MAX)->toDateString()],
            'projects.*.notes' => ['nullable', 'string', 'max:'.$limits['notes']],

            'projects.*.attachments' => ['nullable', 'array', 'max:'.$limits['files']],
            'projects.*.attachments.*' => [
                'file',
                'mimes:'.implode(',', $limits['file_extensions']),
                'max:'.$limits['file_kilobytes'],
            ],
        ];

        foreach ($this->projectsInput() as $index => $project) {
            $service = $this->serviceOf($project);

            if ($service !== null) {
                $rules = [...$rules, ...$this->serviceRules("projects.{$index}", $service)];
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $limits = config('inquiry.limits');
        $messages = [
            'projects.required' => 'Add at least one project.',
            'projects.min' => 'Add at least one project.',
            'projects.max' => 'An inquiry can only have one project.',
            'projects.*.name.required' => 'Give this project a name.',
            'projects.*.name.max' => "Keep the project name to {$limits['name']} characters or fewer.",
            'projects.*.serviceId.required' => 'Choose what you need.',
            'projects.*.serviceId.exists' => 'That choice is no longer available.',
            'projects.*.stones.max' => "A project can have up to {$limits['stone_rows']} stone rows.",
            'projects.*.stones.prohibited' => 'This service needs no stones.',
            'projects.*.stones.*.stoneId.exists' => 'That stone is no longer available.',
            'projects.*.stones.*.quantity.required' => 'Enter how many pieces you need.',
            'projects.*.stones.*.quantity.integer' => 'Quantity has to be a whole number of pieces.',
            'projects.*.stones.*.quantity.min' => "Enter at least {$limits['quantity_min']} piece.",
            'projects.*.stones.*.quantity.max' => "Enter {$limits['quantity_max']} pieces or fewer.",
            'projects.*.stones.*.quantity.prohibited' => 'This service does not ask how many pieces.',
            'projects.*.answers.array' => 'This service does not ask that.',
            'projects.*.answers.prohibited' => 'This service does not ask that.',
            'projects.*.brands.max' => 'Choose fewer brands.',
            'projects.*.brands.*.exists' => 'That brand is no longer available.',
            'projects.*.brands.*.distinct' => 'That brand is already chosen.',
            'projects.*.deliveryMethod.Illuminate\Validation\Rules\Enum' => 'Choose how you would like to get it.',
            'projects.*.addressId.integer' => 'Choose one of your saved addresses.',
            'projects.*.addressId.prohibited' => 'Enter the address with the address picker.',
            'projects.*.keepAddress.prohibited' => 'Enter the address with the address picker.',
            'projects.*.address.prohibited' => 'Choose one of your saved addresses.',
            ...$this->phAddressMessages('projects.*.address'),
            'projects.*.neededBy.after_or_equal' => 'Target deadline cannot be in the past.',
            'projects.*.neededBy.before_or_equal' => 'Choose a date within the next '.self::DEADLINE_YEARS_MAX.' years.',
            'projects.*.neededBy.date_format' => 'Choose a date from the calendar.',
            'projects.*.notes.max' => "Keep the notes to {$limits['notes']} characters or fewer.",
            'projects.*.attachments.max' => "You can attach up to {$limits['files']} files.",
            'projects.*.attachments.*.mimes' => 'Attachments must be PDF, PNG, JPG or JPEG.',
            'projects.*.attachments.*.max' => 'Attachments must be 10 MB or smaller.',
        ];

        // How many pieces keeps its own words, above.
        foreach ($this->questions()->reject(fn (DetailField $field): bool => $field->slug === DetailField::QUANTITY) as $field) {
            $key = $field->scope === DetailFieldScope::Stone
                ? 'projects.*.stones.*.'.$field->stoneInputKey()
                : "projects.*.answers.{$field->slug}";

            foreach ($this->questionMessages($field) as $rule => $message) {
                $messages["{$key}.{$rule}"] = $message;
            }
        }

        return $messages;
    }

    /**
     * The rules that depend on what the project is for.
     *
     * A project needs a stone, or a stone left to Regray to recommend, unless
     * its service asks for none; then it may not ask for a recommendation
     * either. A service no longer offered, or whose service type is not, is
     * refused unless this inquiry already has it (heldServiceSlugs()).
     *
     * Every stone has to be offered for the project's service: shown on
     * Regray, its primary type linked to the service and switched on. The
     * error is on the stone's row. An edit that keeps its service keeps the
     * stones it already has (heldStoneSlugs()) whatever has changed since: a
     * stone switched off, archived, of an archived brand, or of a type the
     * service no longer takes. Changing the service holds none.
     *
     * An answer that means "same as the stone" (`links_to_stone`) needs a stone
     * row to be the same as, so it is refused while Regray is to recommend one.
     *
     * Every project needs a date, because sending the inquiry books it as a
     * schedule, for 10:00, when the showroom opens.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $projects = $this->projectsInput();
                $held = $this->heldServiceSlugs();
                $heldStones = $this->heldStoneSlugs();
                $shownStones = $this->shownStoneSlugsFor($projects);
                $stoneTypeIds = $this->stoneTypeIdsFor($projects);
                $stoneBrands = $this->stoneBrandSlugsFor($projects);

                foreach ($projects as $index => $project) {
                    /** @var Service $service */
                    $service = $this->serviceOf($project);
                    $asksForAdvice = filter_var($project['stoneAdvice'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    if (! $service->isOffered() && ! in_array($service->slug, $held, true)) {
                        $validator->errors()->add("projects.{$index}.serviceId", 'That choice is no longer available.');
                    }

                    // A new stone's type has to be switched on; an inactive one is off the Inquire page.
                    $linked = $service->stoneTypes->where('is_active', true)->modelKeys();
                    // The stones it already has stay while it keeps its service, whatever has changed since.
                    $keepsService = in_array($service->slug, $held, true);
                    // The Brand answer keeps the stones to those brands; No preference keeps none out.
                    $chosenBrands = filter_var($project['brandNoPreference'] ?? false, FILTER_VALIDATE_BOOLEAN) ? [] : (array) ($project['brands'] ?? []);

                    foreach ($project['stones'] ?? [] as $row => $stone) {
                        if ($keepsService && in_array($stone['stoneId'], $heldStones, true)) {
                            continue;
                        }

                        if (! in_array($stone['stoneId'], $shownStones, true)) {
                            $validator->errors()->add("projects.{$index}.stones.{$row}.stoneId", 'That stone is no longer available.');
                        } elseif (! in_array($stoneTypeIds[$stone['stoneId']] ?? null, $linked, true)) {
                            $validator->errors()->add("projects.{$index}.stones.{$row}.stoneId", 'That stone is not offered for this service.');
                        } elseif ($chosenBrands !== [] && ! in_array($stoneBrands[$stone['stoneId']] ?? null, $chosenBrands, true)) {
                            $validator->errors()->add("projects.{$index}.stones.{$row}.stoneId", "That stone isn't from the brands you chose.");
                        }
                    }

                    if (empty($project['neededBy'])) {
                        $validator->errors()->add("projects.{$index}.neededBy", 'Say when this is needed.');
                    }

                    $this->checkLeadTime($validator, $index, $project, $service);

                    $this->checkBrands($validator, $index, $project);
                    $this->checkFulfillment($validator, $index, $project, $service);

                    if (! $service->requires_stones && $asksForAdvice) {
                        $validator->errors()->add("projects.{$index}.stoneAdvice", 'This service needs no stones.');
                    }

                    if ($service->requires_stones && empty($project['stones']) && ! $asksForAdvice) {
                        $validator->errors()->add("projects.{$index}.stones", 'Select at least one stone.');
                    }

                    foreach ($this->projectQuestions($service) as $field) {
                        $option = $field->closedOptions()->firstWhere('slug', $project['answers'][$field->slug] ?? null);

                        if ($option?->links_to_stone && empty($project['stones'])) {
                            $validator->errors()->add("projects.{$index}.answers.{$field->slug}", "{$option->name} needs a stone. Choose another ".Str::lower($field->name).'.');
                        }
                    }

                    $this->checkCustomAnswers($validator, $index, $project, $service);
                    $this->checkSizeByShape($validator, $index, $project, $service);
                }
            },
        ];
    }

    /**
     * A custom choice ("Custom shape") has to be described: its description
     * comes as `customAnswers.{slug}`, at most TEXT_MAX characters. Any other
     * choice takes none, so one sent with it is ignored (inquiryInput()). A
     * refusal is shown on the question itself, as the page shows the box
     * under it.
     *
     * @param  array<string, mixed>  $project
     */
    private function checkCustomAnswers(Validator $validator, int $index, array $project, Service $service): void
    {
        foreach ($this->projectQuestions($service) as $field) {
            $picked = $project['answers'][$field->slug] ?? null;
            $option = $field->answer_type === DetailAnswerType::Choice && is_string($picked) ? $field->closedOption($picked) : null;

            if ($option === null || ! $option->is_custom) {
                continue;
            }

            $text = trim((string) ($project['customAnswers'][$field->slug] ?? ''));

            if ($text === '') {
                $validator->errors()->add("projects.{$index}.answers.{$field->slug}", 'Describe your '.Str::lower($option->name).'.');
            } elseif (mb_strlen($text) > self::TEXT_MAX) {
                $validator->errors()->add("projects.{$index}.answers.{$field->slug}", 'Keep the description to '.self::TEXT_MAX.' characters or fewer.');
            }
        }
    }

    /**
     * Where a service asks both the table shape and the furniture size, the
     * size is written the way the shape asks it (FurnitureSize): a round
     * table's diameter, or width x length. A shape that asks it in the
     * client's own words, or no shape yet, leaves it free; so does a size an
     * edit already has.
     *
     * @param  array<string, mixed>  $project
     */
    private function checkSizeByShape(Validator $validator, int $index, array $project, Service $service): void
    {
        $asked = $this->projectQuestions($service)->keyBy('slug');
        $shape = $asked->get(DetailField::TABLE_SHAPE);
        $size = trim((string) ($project['answers'][DetailField::SIZE] ?? ''));
        $picked = $project['answers'][DetailField::TABLE_SHAPE] ?? null;

        if ($shape === null || ! $asked->has(DetailField::SIZE) || $size === '' || ! is_string($picked)) {
            return;
        }

        $kind = $shape->closedOption($picked)?->size_kind;

        if ($kind === null || in_array($size, $this->heldAnswers()[DetailField::SIZE] ?? [], true)) {
            return;
        }

        $problem = FurnitureSize::problem($kind, $size);

        if ($problem !== null) {
            $validator->errors()->add("projects.{$index}.answers.".DetailField::SIZE, $problem);
        }
    }

    /**
     * A service that takes time to make (`lead_days`) can't be asked for
     * sooner: the date is at least that many days from today, in the
     * showroom's time zone. An edit may keep the date it already has
     * (heldDeadline()).
     *
     * @param  array<string, mixed>  $project
     */
    private function checkLeadTime(Validator $validator, int $index, array $project, Service $service): void
    {
        $date = $project['neededBy'] ?? null;
        $lead = (int) $service->lead_days;

        if (! is_string($date) || $date === '' || $lead === 0 || $date === $this->heldDeadline()) {
            return;
        }

        if ($date < now(ScheduleService::timezone())->addDays($lead)->toDateString()) {
            $validator->errors()->add("projects.{$index}.neededBy", self::leadTimeMessage($lead));
        }
    }

    /** What the page says of a date sooner than a service's lead time; inquiry-flow.ts words it the same. */
    public static function leadTimeMessage(int $days): string
    {
        return 'Choose a date at least '.$days.' '.($days === 1 ? 'day' : 'days').' from today.';
    }

    /**
     * Brands or No preference, one or the other, never both and never neither.
     *
     * @param  array<string, mixed>  $project
     */
    private function checkBrands(Validator $validator, int $index, array $project): void
    {
        $brands = is_array($project['brands'] ?? null) ? $project['brands'] : [];
        $noPreference = filter_var($project['brandNoPreference'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($brands === [] && ! $noPreference) {
            $validator->errors()->add("projects.{$index}.brands", 'Choose at least one brand, or No preference.');
        } elseif ($brands !== [] && $noPreference) {
            $validator->errors()->add("projects.{$index}.brands", 'Choose brands or No preference, not both.');
        }
    }

    /**
     * What the service asks about getting the work to the client: a method
     * and, for a delivery, an address (`delivery_or_pickup`); no method and an
     * installation address (`installed`). A pickup takes no address.
     *
     * @param  array<string, mixed>  $project
     */
    private function checkFulfillment(Validator $validator, int $index, array $project, Service $service): void
    {
        $method = DeliveryMethod::tryFrom((string) ($project['deliveryMethod'] ?? ''));
        $asksMethod = $service->fulfillment->asksDeliveryMethod();

        if ($asksMethod && $method === null) {
            $validator->errors()->add("projects.{$index}.deliveryMethod", 'Choose how you would like to get it.');

            return;
        }

        if (! $asksMethod && $method !== null) {
            $validator->errors()->add("projects.{$index}.deliveryMethod", 'This service is installed on site, so there is no delivery method to choose.');

            return;
        }

        if ($method === DeliveryMethod::Pickup) {
            if ($this->sentAddress($project)) {
                $validator->errors()->add("projects.{$index}.address", 'A pickup needs no address.');
            }

            return;
        }

        $address = $this->addressSource() === self::ADDRESS_FROM_BOOK
            ? $this->addressFromBook($validator, $index, $project, $asksMethod)
            : $this->addressFromPicker($validator, $index, $asksMethod);

        if ($address !== null) {
            $this->projectAddresses[$index] = $address;
        }
    }

    /** @param  array<string, mixed>  $project */
    private function sentAddress(array $project): bool
    {
        return ! empty($project['address']) || ! empty($project['addressId']) || filter_var($project['keepAddress'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * One of the client's own saved addresses, or, when editing, the address
     * the inquiry already has (`keepAddress`).
     *
     * @param  array<string, mixed>  $project
     */
    private function addressFromBook(Validator $validator, int $index, array $project, bool $isDelivery): ?PhAddress
    {
        if (filter_var($project['keepAddress'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $held = $this->heldAddress();

            if ($held === null) {
                $validator->errors()->add("projects.{$index}.addressId", 'Choose one of your saved addresses.');
            }

            return $held;
        }

        $id = $project['addressId'] ?? null;

        if ($id === null || $id === '') {
            $validator->errors()->add("projects.{$index}.addressId", $isDelivery ? 'Choose the delivery address.' : 'Choose the installation address.');

            return null;
        }

        $saved = ClientAddress::query()->where('user_id', $this->user()?->id)->whereKey((int) $id)->first();

        if ($saved === null) {
            $validator->errors()->add("projects.{$index}.addressId", 'That address is no longer in your address book. Choose another.');

            return null;
        }

        return $saved->address();
    }

    /** The address staff entered with the address picker, checked as places. */
    private function addressFromPicker(Validator $validator, int $index, bool $isDelivery): ?PhAddress
    {
        if (! is_array($this->input("projects.{$index}.address"))) {
            $validator->errors()->add("projects.{$index}.address", $isDelivery ? 'Enter the delivery address.' : 'Enter the installation address.');

            return null;
        }

        return $this->checkPhAddress($validator, "projects.{$index}.address");
    }

    /**
     * Where a client's own addresses come from: the address book. Staff
     * requests enter one with the picker.
     */
    protected function addressSource(): string
    {
        return self::ADDRESS_FROM_PICKER;
    }

    /** The address the inquiry being edited already has, which an edit may keep. A new inquiry has none. */
    protected function heldAddress(): ?PhAddress
    {
        return null;
    }

    /**
     * The services a project may keep although they are no longer offered: an edit
     * answers with the ones its inquiry already has. A new inquiry has none.
     *
     * @return list<string>
     */
    protected function heldServiceSlugs(): array
    {
        return [];
    }

    /**
     * The stones a project may keep although they are no longer offered: an
     * edit's own, while it keeps its service. A new inquiry has none.
     *
     * @return list<string>
     */
    protected function heldStoneSlugs(): array
    {
        return [];
    }

    /**
     * The brands a project may keep although they are no longer offered: an
     * edit's own. A new inquiry has none.
     *
     * @return list<string>
     */
    protected function heldBrandSlugs(): array
    {
        return [];
    }

    /**
     * The date a project may keep although it is now sooner than its
     * service's lead time: an edit's own (`Y-m-d`). A new inquiry has none.
     */
    protected function heldDeadline(): ?string
    {
        return null;
    }

    /**
     * The answers a project may keep although they are no longer offered, by
     * question slug: an edit's own edge profiles and finishes (by name) and
     * project answers (as the form sends them), since switched off, archived
     * or taken off the list. A new inquiry has none.
     *
     * @return array<string, list<string>>
     */
    protected function heldAnswers(): array
    {
        return [];
    }

    /**
     * The projects as sent, before validation has had its say: only the
     * entries that are arrays, by their index.
     *
     * @return array<int, array<string, mixed>>
     */
    private function projectsInput(): array
    {
        $projects = $this->input('projects', []);

        return is_array($projects) ? array_filter($projects, 'is_array') : [];
    }

    /**
     * The service a project names, with what its rules need, or null when it
     * names none that exists.
     *
     * @param  array<string, mixed>  $project
     */
    private function serviceOf(array $project): ?Service
    {
        $this->services ??= Service::query()
            ->with(['serviceType', 'stoneTypes:stone_types.id,stone_types.is_active', 'detailFields.options.services:services.id'])
            ->whereIn('slug', array_values(array_unique(array_filter(
                array_column($this->projectsInput(), 'serviceId'),
                'is_string',
            ))))
            ->get()
            ->keyBy('slug');

        $slug = $project['serviceId'] ?? null;

        return is_string($slug) ? $this->services->get($slug) : null;
    }

    /**
     * Every question a service can ask, for the messages.
     *
     * @return Collection<int, DetailField>
     */
    private function questions(): Collection
    {
        return $this->questions ??= DetailField::query()->orderBy('sort_order')->get();
    }

    /**
     * The project-scope questions a service asks, in order.
     *
     * @return Collection<int, DetailField>
     */
    private function projectQuestions(Service $service): Collection
    {
        return $service->detailFields
            ->filter(fn (DetailField $field): bool => $field->scope === DetailFieldScope::Project)
            ->values();
    }

    /**
     * The rules a project's service adds: which stone-row answers it takes,
     * whether it takes stones at all, and its project answers.
     *
     * @return array<string, array<int, mixed>>
     */
    private function serviceRules(string $prefix, Service $service): array
    {
        $rules = [];
        $asked = $service->detailFields->keyBy('slug');

        if (! $service->requires_stones) {
            $rules["{$prefix}.stones"] = ['prohibited'];
        }

        foreach (DetailField::STONE_INPUT_KEYS as $slug => $key) {
            $field = $asked->get($slug);

            $rules["{$prefix}.stones.*.{$key}"] = $field === null
                ? ['prohibited']
                : [$field->isRequiredByService() ? 'required' : 'nullable', ...$this->answerRules($field, $service)];
        }

        $questions = $this->projectQuestions($service);

        $rules["{$prefix}.answers"] = $questions->isEmpty()
            ? ['prohibited']
            : ['nullable', 'array:'.$questions->pluck('slug')->implode(',')];

        foreach ($questions as $field) {
            $rules["{$prefix}.answers.{$field->slug}"] = [$field->isRequiredByService() ? 'required' : 'nullable', ...$this->answerRules($field, $service)];
        }

        return $rules;
    }

    /**
     * What one answer has to be, by its question's answer type. A closed list
     * is checked against its active options (edge profile and finish against
     * their own tables, by name, neither switched off nor archived; a
     * question's own choices against those offered for the service); a free
     * entry only by its type. An edit may also keep the answer it already has
     * (heldAnswers()), offered or not.
     *
     * @return list<mixed>
     */
    private function answerRules(DetailField $field, Service $service): array
    {
        if ($field->slug === DetailField::QUANTITY) {
            $limits = config('inquiry.limits');

            return ['integer', 'min:'.$limits['quantity_min'], 'max:'.$limits['quantity_max']];
        }

        if ($field->isMeasurement()) {
            return ['string', $field->slug === DetailField::SLAB_SIZE ? MeasurementRule::slabSize() : MeasurementRule::thickness()];
        }

        $held = $this->heldAnswers()[$field->slug] ?? [];

        if ($field->answersFromCatalog()) {
            $table = $field->slug === DetailField::EDGE_PROFILE ? 'edge_profiles' : 'finishes';

            // Archived from its Products page is no longer offered, as switched off isn't.
            return ['string', Rule::exists($table, 'name')->where(fn ($rows) => $rows
                ->where(fn ($offered) => $offered->where('is_active', true)->whereNull('deleted_at'))
                ->orWhereIn('name', $held))];
        }

        $listed = $field->allows_custom ? [] : [Rule::in([...$field->suggestions()->pluck('name')->all(), ...$held])];

        return match ($field->answer_type) {
            DetailAnswerType::Text => ['string', 'max:'.self::TEXT_MAX, ...$listed],
            DetailAnswerType::Number => ['numeric', 'gt:0', 'max:'.self::NUMBER_MAX, ...$listed],
            DetailAnswerType::Choice => ['string', Rule::in([...$field->closedOptions($service->id)->pluck('slug')->all(), ...$held])],
            DetailAnswerType::Boolean => ['boolean'],
        };
    }

    /**
     * What the page says when an answer is refused, worded from the
     * question's name. inquiry-schema.ts words them the same way.
     *
     * @return array<string, string>
     */
    private function questionMessages(DetailField $field): array
    {
        $name = Str::lower($field->name);
        $fromList = "Choose the {$name} from the list.";
        $number = 'Enter a number greater than 0.';

        return [
            'required' => in_array($field->answer_type, [DetailAnswerType::Text, DetailAnswerType::Number], true)
                ? "Enter the {$name}."
                : "Choose the {$name}.",
            'prohibited' => "This service does not ask for the {$name}.",
            'string' => $field->answer_type === DetailAnswerType::Text ? "Enter the {$name}." : $fromList,
            'max' => $field->answer_type === DetailAnswerType::Number
                ? 'Enter a smaller number.'
                : "Keep the {$name} to ".self::TEXT_MAX.' characters or fewer.',
            'numeric' => $number,
            'gt' => $number,
            'in' => $fromList,
            'exists' => $fromList,
            'boolean' => 'Choose yes or no.',
        ];
    }

    /**
     * Which of the stones the projects name are shown on Regray: switched
     * on, not archived, and of a brand not archived.
     *
     * @param  array<int, array<string, mixed>>  $projects
     * @return list<string>
     */
    private function shownStoneSlugsFor(array $projects): array
    {
        $slugs = [];

        foreach ($projects as $project) {
            foreach ($project['stones'] ?? [] as $stone) {
                $slugs[] = $stone['stoneId'];
            }
        }

        /** @var list<string> */
        return Stone::query()->active()->whereIn('slug', array_unique($slugs))->pluck('slug')->all();
    }

    /**
     * The primary stone type of every stone the projects name, by slug.
     *
     * @param  array<int, array<string, mixed>>  $projects
     * @return array<string, int>
     */
    private function stoneTypeIdsFor(array $projects): array
    {
        $slugs = [];

        foreach ($projects as $project) {
            foreach ($project['stones'] ?? [] as $stone) {
                $slugs[] = $stone['stoneId'];
            }
        }

        /** @var array<string, int> */
        return Stone::query()->withTrashed()->whereIn('slug', array_unique($slugs))->pluck('stone_type_id', 'slug')->all();
    }

    /**
     * Each picked stone's brand, by the stone's slug.
     *
     * @param  array<int, array<string, mixed>>  $projects
     * @return array<string, string>
     */
    private function stoneBrandSlugsFor(array $projects): array
    {
        $slugs = [];

        foreach ($projects as $project) {
            foreach ($project['stones'] ?? [] as $stone) {
                $slugs[] = $stone['stoneId'];
            }
        }

        /** @var array<string, string> */
        return Stone::query()->withTrashed()->join('brands', 'brands.id', '=', 'stones.brand_id')
            ->whereIn('stones.slug', array_unique($slugs))
            ->select(['stones.slug as stone_slug', 'brands.slug as brand_slug'])
            ->pluck('brand_slug', 'stone_slug')->all();
    }

    /**
     * The validated request in the shape the service writes: slugs and names
     * already turned into ids, a stone row holding only the answers its
     * service asks, the project answers as their rows, and the files collected
     * under each project.
     *
     * @return array{projects: list<ProjectInput>}
     */
    public function inquiryInput(): array
    {
        /** @var list<array<string, mixed>> $projects */
        $projects = array_values($this->validated('projects'));

        $stoneSlugs = [];

        foreach ($projects as $project) {
            foreach ($project['stones'] ?? [] as $stone) {
                $stoneSlugs[] = $stone['stoneId'];
            }
        }

        $stoneIds = Stone::query()->withTrashed()->whereIn('slug', array_unique($stoneSlugs))->pluck('id', 'slug');
        // Archived ones too: an edit may keep the one it has (heldAnswers()).
        $edgeProfileIds = EdgeProfile::query()->withTrashed()->pluck('id', 'name');
        $finishIds = Finish::query()->withTrashed()->pluck('id', 'name');
        $quantityLimit = (int) config('inquiry.limits.quantity_max');

        $brandIds = Brand::query()->pluck('id', 'slug');
        $input = [];

        foreach ($projects as $index => $project) {
            /** @var Service $service */
            $service = $this->serviceOf($project);
            $asked = $service->detailFields->keyBy('slug');
            $deliveryMethod = $service->fulfillment->asksDeliveryMethod() ? DeliveryMethod::from($project['deliveryMethod']) : null;
            $noPreference = filter_var($project['brandNoPreference'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $stones = [];

            // A stone holds one row per set of answers, so a repeated row adds to it.
            foreach ($project['stones'] ?? [] as $stone) {
                $size = $asked->has(DetailField::SLAB_SIZE) && filled($stone['slabSize'] ?? null) ? SizeMeasurement::parse($stone['slabSize']) : null;
                $thickness = $asked->has(DetailField::THICKNESS) && filled($stone['thickness'] ?? null) ? ThicknessMeasurement::parse($stone['thickness']) : null;
                $edgeProfileId = $asked->has(DetailField::EDGE_PROFILE) && filled($stone['edge'] ?? null) ? $edgeProfileIds[$stone['edge']] : null;
                $finishId = $asked->has(DetailField::FINISH) && filled($stone['finish'] ?? null) ? $finishIds[$stone['finish']] : null;
                // By the parsed measurements, so "160x320 CM" and "160 x 320 cm" are one row.
                $row = "{$stone['stoneId']}::{$edgeProfileId}::{$finishId}::{$size?->key()}::{$thickness?->key()}";

                $stones[$row] = [
                    'stone_id' => $stoneIds[$stone['stoneId']],
                    'edge_profile_id' => $edgeProfileId,
                    'finish_id' => $finishId,
                    // Only the slab services ask how many pieces; elsewhere the area says how much.
                    'quantity' => $asked->has(DetailField::QUANTITY)
                        ? min(($stones[$row]['quantity'] ?? 0) + (int) $stone['quantity'], $quantityLimit)
                        : null,
                    'slab_width' => $size?->width,
                    'slab_length' => $size?->length,
                    'slab_unit' => $size?->unit,
                    'thickness' => $thickness?->value,
                    'thickness_unit' => $thickness?->unit,
                ];
            }

            $answers = [];

            foreach ($this->projectQuestions($service) as $field) {
                $value = $project['answers'][$field->slug] ?? null;

                if ($value !== null && $value !== '') {
                    $custom = $project['customAnswers'][$field->slug] ?? null;
                    $answers[] = $field->answerAttributes($value, is_string($custom) ? $custom : null);
                }
            }

            $files = [];

            foreach ($project['attachments'] ?? [] as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }

            $input[] = [
                'name' => $project['name'],
                'service_id' => $service->id,
                'stones' => array_values($stones),
                'answers' => $answers,
                'brand_ids' => $noPreference ? [] : array_values(array_map(fn (string $slug): int => $brandIds[$slug], $project['brands'] ?? [])),
                'brand_no_preference' => $noPreference,
                'delivery_method' => $deliveryMethod,
                // A pickup has no address; a delivery and an installation, the one checked in after().
                'address' => $deliveryMethod === DeliveryMethod::Pickup ? null : $this->projectAddresses[$index],
                'deadline_date' => $project['neededBy'],
                'notes' => $project['notes'] ?? null,
                // Choosing a stone answers the question, so the two never stand together.
                'stone_advice_requested' => $service->requires_stones && $stones === [] && filter_var($project['stoneAdvice'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'files' => $files,
            ];
        }

        return ['projects' => $input];
    }
}
