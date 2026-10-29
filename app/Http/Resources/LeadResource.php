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
 * how to reach them, its status and history (with the notes), the inquiries
 * linked to it (each saying whether it has a group chat), and the actions this
 * reader has. Staff only; nothing here ever reaches a client.
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
     * @return array{reference: string, status: string, createdAt: string, client: array{id: int, name: string}, contact: string|null, email: string|null, marketingUser: string, inquiries: list<array{id: int, reference: string, name: string, hasGroup: bool}>, history: list<array{status: string, note: string|null, at: string}>, actions: list<string>, canCreateInquiry: bool}
     */
    public function toArray(Request $request): array
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'createdAt' => $this->created_at->toIso8601String(),
            'client' => ['id' => $this->client->id, 'name' => $this->client->name],
            'contact' => ClientService::phone(null, $this->client),
            'email' => ClientService::email(null, $this->client),
            'marketingUser' => $this->marketingUser->name,
            'inquiries' => array_values($this->inquiries->map(fn (Inquiry $inquiry): array => [
                'id' => $inquiry->id,
                'reference' => $inquiry->reference,
                'name' => $inquiry->name,
                'hasGroup' => $inquiry->conversation !== null,
            ])->all()),
            'history' => array_values($this->history->map(fn (LeadStatusHistory $entry): array => [
                'status' => $entry->to_status->value,
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

        if ($this->status === LeadStatus::Idle && Gate::forUser($viewer)->allows('update', $this->resource)) {
            array_push($actions, 'won', 'lost');
        }

        return $actions;
    }
}
