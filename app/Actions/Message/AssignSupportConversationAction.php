<?php

namespace App\Actions\Message;

use App\Events\Message\MessageCreated;
use App\Events\Message\SupportThreadClaimed;
use App\Events\Message\SupportThreadReleased;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\LeadService;
use App\Services\Message\MessageService;
use App\Services\Message\SupportConversationService;
use Illuminate\Support\Facades\DB;

/**
 * Claim, release and reassign a Regray Team thread.
 *
 * Each change runs in one transaction, which is where the service's row lock
 * lives. The broadcasts go out after it commits, so nobody hears about a claim
 * that was rolled back.
 *
 * Claiming or being handed a thread makes the new holder the client's
 * marketing contact, in the same transaction: the client gets a lead held by
 * them, or their idle lead moves to them, and the client's inquiries that had
 * no lead are linked to it (LeadService::assign()).
 */
class AssignSupportConversationAction
{
    public function __construct(
        public readonly SupportConversationService $supportService,
        public readonly MessageService $messageService,
        public readonly LeadService $leadService,
    ) {}

    public function claim(User $user, Conversation $conversation): Conversation
    {
        $notice = DB::transaction(function () use ($user, $conversation): Message {
            $notice = $this->supportService->claim($user, $conversation);
            $this->leadService->assign($this->client($conversation), $user);

            return $notice;
        });

        return $this->announceClaim($conversation, $notice, $user, 'assigned');
    }

    public function release(User $user, Conversation $conversation): Conversation
    {
        $notice = DB::transaction(fn (): Message => $this->supportService->release($user, $conversation));
        $fresh = $conversation->fresh() ?? $conversation;

        broadcast(new MessageCreated($notice, $fresh));
        event(new SupportThreadReleased($fresh->id));
        $this->messageService->announceActivity($fresh, 'released');

        return $fresh;
    }

    public function reassign(User $admin, Conversation $conversation, User $target): Conversation
    {
        $notice = DB::transaction(function () use ($admin, $conversation, $target): Message {
            $notice = $this->supportService->reassign($admin, $conversation, $target);
            $this->leadService->assign($this->client($conversation), $target);

            return $notice;
        });

        return $this->announceClaim($conversation, $notice, $target, 'reassigned');
    }

    private function client(Conversation $conversation): User
    {
        return User::query()->findOrFail($conversation->support_client_id);
    }

    private function announceClaim(Conversation $conversation, Message $notice, User $assignee, string $event): Conversation
    {
        $fresh = $conversation->fresh() ?? $conversation;

        broadcast(new MessageCreated($notice, $fresh));
        event(new SupportThreadClaimed($fresh->id, ['id' => $assignee->id, 'name' => $assignee->name], $event));
        $this->messageService->announceActivity($fresh, $event);

        return $fresh;
    }
}
