<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InquiryDetailsService
{
    public function __construct(
        public readonly InquiryRepositoryInterface $inquiryRepository,
        public readonly ConversationRepositoryInterface $conversations,
        public readonly ConversationMemberRepositoryInterface $members,
    ) {}

    /**
     * The inquiry's group chat, only when the viewer may open it
     * (ConversationPolicy::view: a member, or staff who read the inquiry), for
     * the "Message the team" button.
     *
     * @return array{id: int, name: string}|null
     */
    public function conversationFor(User $viewer, Inquiry $inquiry): ?array
    {
        $conversation = $this->conversations->groupForInquiry($inquiry->id);

        if ($conversation === null || ! $viewer->can('view', $conversation)) {
            return null;
        }

        return ['id' => $conversation->id, 'name' => (string) $conversation->name];
    }

    /**
     * An inquiry this staff member reads (Inquiry::scopeVisibleTo()), by its
     * reference, with everything the details page and the edit page show.
     *
     * One they do not read is the same 404 as one that does not exist, so the
     * answer never says which references are in use.
     *
     * @throws ModelNotFoundException
     */
    public function forStaff(User $viewer, string $reference): Inquiry
    {
        return $this->inquiryRepository->findByReferenceWithDetails($viewer, $reference)
            ?? throw (new ModelNotFoundException)->setModel(Inquiry::class, [$reference]);
    }

    /**
     * The details page's inquiry for this reader: their own inquiry for a
     * client, one they read for staff.
     *
     * @throws ModelNotFoundException
     */
    public function forViewer(User $viewer, string $reference): Inquiry
    {
        return $viewer->isClient() ? $this->forClient($viewer, $reference) : $this->forStaff($viewer, $reference);
    }

    /**
     * One of this client's inquiries with everything the details page shows.
     *
     * A reference that is not theirs is the same 404 as one that does not exist,
     * so the answer never says which references are in use.
     *
     * @throws ModelNotFoundException
     */
    public function forClient(User $client, string $reference): Inquiry
    {
        return $this->inquiryRepository->findForClientByReferenceWithDetails($client, $reference)
            ?? throw (new ModelNotFoundException)->setModel(Inquiry::class, [$reference]);
    }
}
