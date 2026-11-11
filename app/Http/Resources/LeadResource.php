<?php

namespace App\Http\Resources;

use App\Enums\LeadStatus;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A lead as a row of the Leads page, and what its View shows: its client and
 * how to reach them, the tally of its inquiries' decisions, its log (with the
 * notes and the inquiry each decision was on), the inquiries linked to it
 * (each with marketing's decision, whether it has a group chat, whether this
 * reader may decide it now, and whether they may make its group chat: only
 * once it is won, InquiryPolicy::createGroup()), and the actions this reader
 * has. Staff only; nothing here ever reaches a client.
 *
 * Expects the client and their profile, the marketing user, the inquiries with
 * their group chats, and the history loaded
 * (LeadRepositoryInterface::paginateFor()).
 *
 * @mixin Lead
 */
class LeadResource extends JsonResource
{
    /**
     * @return array{reference: string, tally: array{won: int, lost: int, idle: int}, createdAt: string, client: array{id: int, name: string}, contact: string|null, email: string|null, marketingUser: string, inquiries: list<array{id: int, reference: string, name: string, marketingStatus: string, hasGroup: bool, canDecide: bool, canCreateGroup: bool}>, history: list<array{status: string, inquiry: string|null, note: string|null, at: string}>, actions: list<string>, canCreateInquiry: bool}
     */
    public function toArray(Request $request): array
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $mayDecide = Gate::forUser($viewer)->allows('update', $this->resource);

        return [
            'reference' => $this->reference,
            'tally' => $this->tally(),
            'createdAt' => $this->created_at->toIso8601String(),
            'client' => ['id' => $this->client->id, 'name' => $this->client->name],
            'contact' => ClientService::phone(null, $this->client),
            'email' => ClientService::email(null, $this->client),
            'marketingUser' => $this->marketingUser->name,
            'inquiries' => array_values($this->inquiries->map(fn (Inquiry $inquiry): array => [
                'id' => $inquiry->id,
                'reference' => $inquiry->reference,
                'name' => $inquiry->name,
                'marketingStatus' => ($inquiry->marketing_status ?? LeadStatus::Idle)->value,
                'hasGroup' => $inquiry->conversation !== null,
                // Waiting for marketing, and this reader holds the lead.
                'canDecide' => $inquiry->marketing_status === null && $inquiry->sales_status === null && $mayDecide,
                'canCreateGroup' => $inquiry->conversation === null
                    && Gate::forUser($viewer)->allows('createGroup', $inquiry->setRelation('lead', $this->resource)),
            ])->all()),
            'history' => array_values($this->history->map(fn (LeadStatusHistory $entry): array => [
                'status' => $entry->to_status->value,
                'inquiry' => $entry->inquiry?->reference,
                'note' => $entry->note,
                'at' => $entry->changed_at->toIso8601String(),
            ])->all()),
            'actions' => $this->actionsFor($viewer),
            'canCreateInquiry' => Gate::forUser($viewer)->allows('createInquiry', $this->resource),
        ];
    }

    /** @return list<string> */
    private function actionsFor(User $viewer): array
    {
        $actions = Gate::forUser($viewer)->allows('view', $this->resource) ? ['view'] : [];

        if (Gate::forUser($viewer)->allows('update', $this->resource)) {
            $actions[] = 'edit';
        }

        return $actions;
    }
}
