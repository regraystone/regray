<?php

namespace Database\Seeders;

use App\Enums\ConversationMemberRole;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Enums\SystemEvent;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\Message;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Services\Message\SupportConversationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSampleConversations();
        $this->seedRegrayTeamThreads();
    }

    /**
     * The sample groups and direct chats, seen from the admin account ("me").
     */
    private function seedSampleConversations(): void
    {
        $data = require database_path('seeders/data/conversations.php');
        $members = app(ConversationMemberRepositoryInterface::class);

        $personEmails = [
            'maria' => 'maria.alvarez@example.com',
            'daniel' => 'daniel.okafor@example.com',
            'sofia' => 'sofia.bianchi@example.com',
            'kenji' => 'kenji.watanabe@example.com',
            'priya' => 'priya.raman@example.com',
            'lucas' => 'lucas.moreau@example.com',
            'tomas' => 'tomas.lindqvist@example.com',
            'beatriz' => 'beatriz.vasconcelos.cummings@example.com',
            'hassan' => 'hassan.al-farsi@example.com',
            'me' => 'admin@example.com',
        ];

        $meUser = User::where('email', 'admin@example.com')->firstOrFail();

        $scheduleMap = [
            'apt-04' => Schedule::where('title', 'Site ocular')->first()?->id,
            'apt-06' => Schedule::where('title', 'Showroom visit')->where('id', '>', 1)->first()?->id,
            'apt-03' => Schedule::where('title', 'Quotation review')->first()?->id,
            'apt-18' => Schedule::where('title', 'Cancelled demo')->first()?->id,
        ];

        foreach ($data['conversations'] as $conversationData) {
            $users = ['me' => $meUser];

            foreach ($conversationData['member_ids'] as $personId) {
                $users[$personId] = User::where('email', $personEmails[$personId])->firstOrFail();
            }

            $directKey = null;

            if ($conversationData['type'] === 'direct') {
                $memberIds = array_map(fn (User $user): int => $user->id, array_values($users));
                sort($memberIds);
                $directKey = hash('sha256', implode(':', $memberIds));
            }

            // TODO(inquiry-binding): groups are not bound to inquiries yet, so
            // the sample groups carry no inquiry either.
            $inquiry = $conversationData['inquiry_id']
                ? Inquiry::where('reference', strtoupper($conversationData['inquiry_id']))->first()
                : null;

            $conversation = Conversation::firstOrCreate(
                ['direct_key' => $directKey, 'name' => $conversationData['name'], 'type' => $conversationData['type']],
                [
                    'created_by' => $meUser->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            foreach ($users as $personId => $user) {
                $members->upsert(
                    $conversation->id,
                    $user->id,
                    $personId === 'me' && $conversationData['type'] === 'group' ? ConversationMemberRole::Admin : ConversationMemberRole::Member,
                );
            }

            $lastMessage = null;
            $messages = [];

            foreach ($conversationData['messages'] as $messageData) {
                $kind = $messageData['kind'];
                $scheduleId = $kind === 'schedule' ? ($scheduleMap[$messageData['schedule_id'] ?? ''] ?? null) : null;

                // A card whose visit or inquiry is not in the sample data has
                // nothing to show, so it is left out rather than broken.
                if (($kind === 'schedule' && $scheduleId === null) || ($kind === 'inquiry' && $inquiry === null)) {
                    continue;
                }

                $senderId = $kind === 'text' && isset($messageData['sender_id'])
                    ? $users[$messageData['sender_id']]->id ?? User::where('email', $personEmails[$messageData['sender_id']])->first()?->id
                    : null;

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $senderId,
                    'type' => match ($kind) {
                        'schedule' => MessageType::ScheduleCard,
                        'inquiry' => MessageType::InquiryCard,
                        default => MessageType::Text,
                    },
                    'body' => $kind === 'text' ? ($messageData['body'] ?? null) : null,
                    'inquiry_id' => $kind === 'inquiry' ? $inquiry->id : null,
                    'schedule_id' => $scheduleId,
                    'announced_status' => $kind === 'text' ? null : ($messageData['announced_status'] ?? null),
                    'created_at' => Carbon::parse($messageData['sent_at']),
                ]);

                $messages[] = $message;
                $lastMessage = $message;
            }

            // Everyone has read everything, except that the admin is one message
            // behind on a conversation the sample marks unread.
            foreach ($users as $personId => $user) {
                $pointer = $conversationData['unread'] && $personId === 'me' && count($messages) > 1
                    ? $messages[count($messages) - 2]
                    : $lastMessage;

                if ($pointer !== null) {
                    $members->moveReadPointer($conversation->id, $user->id, $pointer->id);
                    $members->moveDeliveredPointer($conversation->id, $user->id, $pointer->id);
                }
            }
        }
    }

    /**
     * Every client gets their Regray Team thread. Two of them are given a
     * conversation to show both states: the demo client's is waiting in the
     * pool, and the first sample client's has been claimed by marketing.
     */
    private function seedRegrayTeamThreads(): void
    {
        $support = app(SupportConversationService::class);
        $members = app(ConversationMemberRepositoryInterface::class);

        foreach (User::role(Role::Client->value)->get() as $client) {
            $support->ensureFor($client);
        }

        $marketing = User::where('email', 'marketing@example.com')->first();
        $waiting = User::where('email', 'client@example.com')->first();
        $handled = User::where('email', 'rafael.doming@thenorthgatecafe.example')->first();

        if ($waiting !== null) {
            $thread = $support->ensureFor($waiting);

            if (! $thread->messages()->exists()) {
                $this->text($thread, $waiting, 'Hi! Do you have Calacatta marble slabs in stock this month?', now()->subHours(3));
                $last = $this->text($thread, $waiting, 'We need about 12 square metres for a kitchen island.', now()->subHours(3)->addMinute());
                $members->moveReadPointer($thread->id, $waiting->id, $last->id);
            }
        }

        if ($handled !== null && $marketing !== null) {
            $thread = $support->ensureFor($handled);

            // InquirySeeder has already posted this client's project cards
            // here, so only a thread with no conversation yet gets one.
            if (! $thread->messages()->where('type', MessageType::Text)->exists()) {
                $first = $this->text($thread, $handled, 'Good morning — could someone confirm the delivery date for our counters?', now()->subDay());

                $thread->forceFill(['assigned_to' => $marketing->id, 'assigned_at' => now()->subDay()->addMinutes(5)])->save();
                $members->upsert($thread->id, $marketing->id, ConversationMemberRole::Member);

                Message::create([
                    'conversation_id' => $thread->id,
                    'type' => MessageType::System,
                    'system_event' => SystemEvent::Assigned,
                    'system_user_id' => $marketing->id,
                    'created_at' => now()->subDay()->addMinutes(5),
                ]);

                $reply = $this->text($thread, $marketing, 'Good morning! Your counters are scheduled for delivery on Friday.', now()->subDay()->addMinutes(6));

                $members->moveReadPointer($thread->id, $handled->id, $first->id);
                $members->moveReadPointer($thread->id, $marketing->id, $reply->id);
                $members->moveDeliveredPointer($thread->id, $handled->id, $reply->id);
                $members->moveDeliveredPointer($thread->id, $marketing->id, $reply->id);
            }
        }
    }

    private function text(Conversation $conversation, User $sender, string $body, CarbonInterface $at): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'type' => MessageType::Text,
            'body' => $body,
            'created_at' => $at,
        ]);
    }
}
