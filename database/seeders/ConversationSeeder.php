<?php

namespace Database\Seeders;

use App\Models\Agenda;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Inquiry;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/conversations.php');

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

        $agendaMap = [
            'apt-04' => Agenda::where('title', 'Site ocular')->first()?->id,
            'apt-06' => Agenda::where('title', 'Showroom visit')->where('id', '>', 1)->first()?->id,
            'apt-03' => Agenda::where('title', 'Quotation review')->first()?->id,
            'apt-18' => Agenda::where('title', 'Cancelled demo')->first()?->id,
        ];

        foreach ($data['conversations'] as $conversationData) {
            $directKey = null;
            if ($conversationData['type'] === 'direct') {
                $memberIds = array_map(fn ($id) => $id === 'me' ? $meUser->id : User::where('email', $personEmails[$id])->first()->id, $conversationData['member_ids']);
                sort($memberIds);
                $directKey = hash('sha256', implode(':', $memberIds));
            }

            $inquiry = null;
            if ($conversationData['inquiry_id']) {
                $inquiry = Inquiry::where('reference', $conversationData['inquiry_id'])->first();
            }

            $lookupKey = $directKey ?? ('inq_'.($inquiry->id ?? 'none'));
            $conversation = Conversation::firstOrCreate(
                ['direct_key' => $directKey, 'inquiry_id' => $inquiry->id ?? null],
                [
                    'type' => $conversationData['type'],
                    'name' => $conversationData['name'],
                    'created_by' => $meUser->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $members = [];
            foreach ($conversationData['member_ids'] as $personId) {
                $userEmail = $personEmails[$personId];
                $user = User::where('email', $userEmail)->firstOrFail();
                $members[$personId] = $user;

                ConversationMember::query()->firstOrCreate(
                    ['conversation_id' => $conversation->id, 'user_id' => $user->id],
                    ['joined_at' => now()]
                );
            }

            $lastMessage = null;
            $messages = [];
            foreach ($conversationData['messages'] as $messageData) {
                $senderId = null;
                if ($messageData['kind'] === 'text' && isset($messageData['sender_id'])) {
                    $senderEmail = $personEmails[$messageData['sender_id']];
                    $senderId = User::where('email', $senderEmail)->first()?->id;
                }

                $messageType = $messageData['kind'];
                if ($messageType === 'agenda') {
                    $messageType = 'agenda_card';
                } elseif ($messageType === 'inquiry') {
                    $messageType = 'inquiry_card';
                }

                $agendaId = null;
                if ($messageData['kind'] === 'agenda' && isset($messageData['agenda_id'])) {
                    $agendaId = $agendaMap[$messageData['agenda_id']] ?? null;
                }

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $senderId,
                    'type' => $messageType,
                    'body' => $messageData['body'] ?? null,
                    'inquiry_id' => $messageData['kind'] === 'inquiry' ? $inquiry->id : null,
                    'agenda_id' => $agendaId,
                    'announced_status' => $messageData['announced_status'] ?? null,
                    'created_at' => Carbon::parse($messageData['sent_at']),
                ]);

                $messages[$messageData['id']] = $message;
                $lastMessage = $message;
            }

            if ($conversationData['unread'] && count($messages) > 1) {
                $messageList = array_values($messages);
                $unreadMsg = $messageList[count($messageList) - 2];
                foreach ($members as $personId => $user) {
                    if ($personId !== 'me') {
                        ConversationMember::where('conversation_id', $conversation->id)
                            ->where('user_id', $user->id)
                            ->update(['last_read_message_id' => $unreadMsg->id]);
                    }
                }
            } elseif ($lastMessage) {
                foreach ($members as $user) {
                    ConversationMember::where('conversation_id', $conversation->id)
                        ->where('user_id', $user->id)
                        ->update(['last_read_message_id' => $lastMessage->id]);
                }
            }
        }
    }
}
