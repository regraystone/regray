<?php

namespace App\Http\Resources;

use App\Data\PhAddress;
use App\Enums\DetailAnswerType;
use App\Enums\DocumentType;
use App\Models\Brand;
use App\Models\DetailField;
use App\Models\Inquiry;
use App\Models\InquiryDetailAnswer;
use App\Models\InquiryFile;
use App\Models\InquiryStone;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An inquiry as the Inquire page's edit mode is filled from: the one project
 * it is, in the shape of a project on that page.
 *
 * Unlike InquiryDetailsResource, which is for reading, every identifier here is
 * the one the form sends back: the inquiry's and the files' real ids, service
 * and stone slugs, and edge and finish names. Only the client's own attachments,
 * nothing about the client and no file paths.
 *
 * A stone row's answers are null where its service does not ask them; its
 * slab size and thickness are written back as the labels the form holds.
 * `answers` are the project-scope ones by question slug, in the shape the page
 * sends them: free text, a number, a closed option's slug, yes or no.
 *
 * `brands` are the brands it names by slug, empty with `brandNoPreference`;
 * `heldBrands` the same brands as the Brand question lists them, since one
 * may have been archived since.
 * `deliveryMethod` is null for an installed service. `address` is the copy it
 * was sent with (PhAddress), null for a pickup: a client's edit may keep it
 * even if it has since left their address book, and staff start from it in
 * the address picker.
 *
 * `heldServiceType` is the service it already has under its type, so one no longer
 * offered to new inquiries still reads back on the page and can be kept.
 * `heldStones` are its stones as cards, since they may be on no page of stones the
 * reader has reached. `heldChoices` are the listed answers it already has, by
 * question slug, as InquiryDetailFieldResource lists a question's: its edge
 * profiles and finishes, closed options and listed values, which may since
 * have been switched off, archived or taken off their lists. The page lists
 * them beside what is offered, so the edit shows them and can keep them
 * (UpdateInquiryRequest holds them).
 *
 * @mixin Inquiry
 *
 * @phpstan-import-type PhAddressPayload from PhAddress
 */
class InquiryEditResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, serviceTypeId: string|null, serviceId: string|null, heldServiceType: array{id: string, name: string, services: array<int, mixed>}|null, heldStones: array<int, mixed>, heldChoices: object, heldBrands: array<int, mixed>, stoneAdviceRequested: bool, stones: list<array{stoneId: string, quantity: int|null, slabSize: string|null, thickness: string|null, edge: string|null, finish: string|null}>, answers: object, brands: list<string>, brandNoPreference: bool, deliveryMethod: string|null, address: PhAddressPayload|null, notes: string|null, neededBy: string|null, deadlineLocked: bool, deadlineLockedReason: string|null, attachments: list<array{id: int, name: string, sizeBytes: int, mimeType: string, status: string}>}
     */
    public function toArray(Request $request): array
    {
        $schedule = $this->schedule;
        $arrivalAt = $schedule?->arrival_at->setTimezone(ScheduleService::timezone());
        $lockedReason = $schedule === null ? null : app(ScheduleService::class)->deadlineLockedReason($schedule);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'serviceTypeId' => $this->service?->serviceType->slug,
            'serviceId' => $this->service?->slug,
            'heldServiceType' => $this->service === null ? null : [
                'id' => $this->service->serviceType->slug,
                'name' => $this->service->serviceType->name,
                'services' => InquiryServiceResource::collection([$this->service])->resolve($request),
            ],
            'heldStones' => StoneCardResource::collection(
                $this->stones->map(fn (InquiryStone $row) => $row->stone)->unique('id')->values(),
            )->resolve($request),
            'heldChoices' => (object) $this->heldChoices(),
            'stoneAdviceRequested' => $this->stone_advice_requested,
            'stones' => array_values($this->stones->map(fn (InquiryStone $row): array => [
                'stoneId' => $row->stone->slug,
                'quantity' => $row->quantity,
                // As the form sends them back: "160 x 320 cm", "20 mm".
                'slabSize' => $row->slabSizeLabel(),
                'thickness' => $row->thicknessLabel(),
                'edge' => $row->edgeProfile?->name,
                'finish' => $row->finish?->name,
            ])->all()),
            // An object even when empty, as the page's form holds it.
            'answers' => (object) $this->detailAnswers
                ->mapWithKeys(fn (InquiryDetailAnswer $answer): array => [$answer->detailField->slug => $answer->formValue()])
                ->all(),
            'brands' => array_values($this->brands->map(fn (Brand $brand): string => $brand->slug)->all()),
            // As the Brand question lists them, so one archived since still shows and can be kept.
            'heldBrands' => InquiryBrandResource::collection($this->brands)->resolve($request),
            'brandNoPreference' => $this->brand_no_preference,
            'deliveryMethod' => $this->delivery_method?->value,
            'address' => $this->address()?->toPayload(),
            'notes' => $this->notes,
            'neededBy' => $arrivalAt?->toDateString(),
            'deadlineLocked' => $lockedReason !== null,
            'deadlineLockedReason' => $lockedReason,
            'attachments' => array_values($this->files
                ->filter(fn (InquiryFile $file): bool => $file->document_type === DocumentType::ClientAttachment)
                ->map(fn (InquiryFile $file): array => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'sizeBytes' => $file->size_bytes,
                    'mimeType' => $file->mime_type,
                    'status' => 'saved',
                ])->all()),
        ];
    }

    /**
     * The listed answers it already has, by question slug, each once.
     *
     * @return array<string, list<array{value: string, label: string, isAdvisory?: bool, linksToStone?: bool}>>
     */
    private function heldChoices(): array
    {
        $held = [];
        $add = function (string $slug, array $choice) use (&$held): void {
            if (! in_array($choice['value'], array_column($held[$slug] ?? [], 'value'), true)) {
                $held[$slug][] = $choice;
            }
        };

        foreach ($this->stones as $row) {
            if ($row->edgeProfile !== null) {
                $add(DetailField::EDGE_PROFILE, ['value' => $row->edgeProfile->name, 'label' => $row->edgeProfile->name, 'isAdvisory' => $row->edgeProfile->is_advisory]);
            }

            if ($row->finish !== null) {
                $add(DetailField::FINISH, ['value' => $row->finish->name, 'label' => $row->finish->name, 'isAdvisory' => $row->finish->is_advisory]);
            }
        }

        foreach ($this->detailAnswers as $answer) {
            $slug = $answer->detailField->slug;

            if ($answer->option !== null) {
                $add($slug, ['value' => $answer->option->slug, 'label' => $answer->option->name, 'linksToStone' => $answer->option->links_to_stone]);
            } elseif (in_array($answer->detailField->answer_type, [DetailAnswerType::Text, DetailAnswerType::Number], true) && $answer->formValue() !== null) {
                $value = (string) $answer->formValue();
                $add($slug, ['value' => $value, 'label' => $value]);
            }
        }

        /** @var array<string, list<array{value: string, label: string, isAdvisory?: bool, linksToStone?: bool}>> $held */
        return $held;
    }
}
