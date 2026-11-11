<?php

namespace App\Actions\Message;

use App\Events\Message\MessageCreated;
use App\Events\Message\SupportThreadClaimed;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\LeadService;
use App\Services\Message\MessageService;
use App\Services\Message\SupportConversationService;
use Illuminate\Support\Facades\DB;

/**
 * Claim a Regray Team thread. A claim is permanent: the thread, and the
 * client's lead with it, is never released or handed to another marketing
 * user. Only the staff assigned on an inquiry are reassigned.
 *
 * The claim runs in one transaction, which is where the service's row lock
 * lives. The broadcasts go out after it commits, so nobody hears about a claim
 * that was rolled back.
 *
 * Claiming a thread makes the claimer the client's marketing contact, in the
 * same transaction: the client gets a lead held by them, or their idle lead
 * moves to them, and the client's inquiries that had no lead are linked to it
 * (LeadService::assign()).
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
            $this->leadService->assign(User::query()->findOrFail($conversation->support_client_id), $user);

            return $notice;
        });

        $fresh = $conversation->fresh() ?? $conversation;

        broadcast(new MessageCreated($notice, $fresh));
        event(new SupportThreadClaimed($fresh->id, ['id' => $user->id, 'name' => $user->name], 'assigned'));
        $this->messageService->announceActivity($fresh, 'assigned');

        return $fresh;
    }
}
