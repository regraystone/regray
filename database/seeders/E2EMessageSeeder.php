<?php

namespace Database\Seeders;

use App\Data\Message\QuotationCardSnapshot;
use App\Enums\BookedByType;
use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\DeliveryMethod;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use App\Models\Conversation;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryHandler;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Stone;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Services\Message\SupportConversationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Test-only data for the Playwright suite in tests/e2e/message. Runs after
 * DatabaseSeeder and only adds rows; it changes nothing the other seeders made.
 *
 * Writes the ids the specs need to storage/app/e2e/message-fixtures.json.
 */
class E2EMessageSeeder extends Seeder
{
    public const LIST_SIZE = 45;

    public const HISTORY_SIZE = 100;

    /** @var array<string, mixed> */
    private array $fixtures = [];

    private ConversationMemberRepositoryInterface $members;

    private Carbon $clock;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('The E2E seeder only runs with APP_ENV=local or testing.');
        }

        $this->members = app(ConversationMemberRepositoryInterface::class);
        $this->clock = Carbon::now()->subDays(3);

        $marketingB = $this->user('e2e.marketing.b@example.com', 'Marketing B', Role::Marketing);
        $partner = $this->user('e2e.partner@example.com', 'Partner Staff', Role::Technical);
        $lister = $this->user('e2e.lister@example.com', 'List Tester', Role::Technical);
        $freshClient = $this->user('e2e.fresh.client@example.com', 'Fresh Client', Role::Client);
        $cardClient = $this->user('e2e.card.client@example.com', 'Card Client', Role::Client);
        $directClient = $this->user('e2e.direct.client@example.com', 'Direct Client', Role::Client);

        $marketingA = User::query()->where('email', 'marketing@example.com')->firstOrFail();
        $sales = User::query()->where('email', 'sales@example.com')->firstOrFail();

        $this->fixtures['users'] = [
            'marketingA' => $marketingA->email,
            'marketingB' => $marketingB->email,
            'sales' => $sales->email,
            'admin' => 'admin@example.com',
            'client' => 'client@example.com',
            'freshClient' => $freshClient->email,
            'cardClient' => $cardClient->email,
            'directClient' => $directClient->email,
            'lister' => $lister->email,
        ];

        $this->seedSupportClients();
        $this->seedClientScope($cardClient, $marketingA);
        $this->seedLists($lister, $partner);
        $this->seedCards($cardClient, $sales, $marketingA);
        $this->seedChats($marketingA, $marketingB, $sales, $directClient);
        $this->seedBoundInquiries($cardClient, $sales);

        File::ensureDirectoryExists(storage_path('app/e2e'));
        File::put(storage_path('app/e2e/message-fixtures.json'), json_encode($this->fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function user(string $email, string $name, Role $role): User
    {
        return User::query()->where('email', $email)->first()
            ?? User::factory()->role($role)->create(['email' => $email, 'name' => $name]);
    }

    /**
     * One client per assignment scenario, each with an unassigned Regray Team
     * thread holding one message, so no scenario inherits another's claim.
     */
    private function seedSupportClients(): void
    {
        $support = app(SupportConversationService::class);

        foreach (['claim', 'race', 'release', 'reassign', 'names', 'forbidden', 'common'] as $key) {
            $client = $this->user("e2e.support.{$key}@example.com", 'Support '.ucfirst($key), Role::Client);
            $thread = $support->ensureFor($client);
            $message = $this->text($thread, $client, "Hello from support {$key}");
            $this->members->moveReadPointer($thread->id, $client->id, $message->id);

            $this->fixtures['support'][$key] = [
                'email' => $client->email,
                'name' => $client->name,
                'conversationId' => $thread->id,
            ];
        }
    }

    /**
     * Marketing finds only the clients it is authorized for
     * (User::scopeClientsVisibleTo()), so a-access gives Marketing A Card
     * Client's lead: its people search finds Card Client by name. "Client"
     * (client@example.com) is found through the support pool: its Regray Team
     * thread is unassigned (ConversationSeeder).
     */
    private function seedClientScope(User $cardClient, User $marketingA): void
    {
        $lead = Lead::query()->firstOrCreate(
            ['client_id' => $cardClient->id],
            ['reference' => 'LEAD-E2E-CARD', 'marketing_user_id' => $marketingA->id],
        );

        if ($lead->wasRecentlyCreated) {
            $lead->history()->create(['to_status' => LeadStatus::Idle, 'changed_at' => Carbon::now()]);
        }
    }

    /**
     * Exactly LIST_SIZE groups for the list tester. One holds HISTORY_SIZE
     * messages; three are unread by 1, 2 and 3 messages.
     */
    private function seedLists(User $lister, User $partner): void
    {
        $unread = [1 => 1, 2 => 2, 3 => 3];

        for ($n = 1; $n <= self::LIST_SIZE; $n++) {
            $name = $n === self::LIST_SIZE ? 'E2E History' : sprintf('E2E List %02d', $n);
            $group = $this->group($name, [$lister, $partner]);
            $count = $n === self::LIST_SIZE ? self::HISTORY_SIZE : 2 + ($unread[$n] ?? 0);
            $messages = [];

            // The last `unread` messages are the partner's, so the lister's own
            // send does not move their read pointer past them.
            for ($m = 1; $m <= $count; $m++) {
                $byLister = $m % 2 === 0 && $m <= $count - ($unread[$n] ?? 0);
                $messages[] = $this->text($group, $byLister ? $lister : $partner, "{$name} message {$m}");
            }

            $readUpTo = $messages[count($messages) - 1 - ($unread[$n] ?? 0)];
            $this->members->moveReadPointer($group->id, $lister->id, $readUpTo->id);
            $this->members->moveReadPointer($group->id, $partner->id, $messages[count($messages) - 1]->id);

            if ($n === self::LIST_SIZE) {
                $this->fixtures['history'] = [
                    'conversationId' => $group->id,
                    'firstMessage' => "{$name} message 1",
                    'lastMessage' => "{$name} message {$count}",
                ];
            }
        }

        foreach ($unread as $n => $count) {
            $this->fixtures['unread'][] = ['name' => sprintf('E2E List %02d', $n), 'count' => $count];
        }
    }

    /**
     * A group holding an inquiry card for every display status, one with a
     * 100-character project name, and a visit card for every status and
     * booking type.
     */
    private function seedCards(User $client, User $sales, User $marketingA): void
    {
        $group = $this->group('E2E Cards', [$sales, $marketingA, $client]);

        $inquiry = $this->inquiry($client, 'INQ-E2E-CARDS', 'Kitchen island');

        foreach (InquiryStatus::cases() as $status) {
            $this->card($group, ['type' => MessageType::InquiryCard, 'inquiry_id' => $inquiry->id, ...$this->snapshot($status)]);
        }

        $longName = str_repeat('Long project name ', 6);
        $longName = substr($longName, 0, 100);
        $long = $this->inquiry($client, 'INQ-E2E-LONG', $longName);
        $this->card($group, ['type' => MessageType::InquiryCard, 'inquiry_id' => $long->id, ...$this->snapshot(InquiryStatus::Production)]);

        // No stones: the card shows the service in place of the pictures, and no Materials row.
        $serviceOnly = $this->inquiry($client, 'INQ-E2E-SERVICE', 'Countertop repair', withStone: false);
        $this->card($group, ['type' => MessageType::InquiryCard, 'inquiry_id' => $serviceOnly->id, ...$this->snapshot(InquiryStatus::Pending)]);

        $quotation = $this->quotationCard($group, $inquiry, $sales, 'QUO-0002_Regray Stone.pdf');
        $longFileName = substr('QUO-0003_'.str_repeat('Regray Stone kitchen island ', 5), 0, 120).'.pdf';
        $this->quotationCard($group, $long, $sales, $longFileName);

        $schedules = [];

        // A card never announces awaiting sales: the client reads it as pending.
        foreach (array_filter(ScheduleStatus::cases(), fn (ScheduleStatus $status): bool => $status !== ScheduleStatus::AwaitingSales) as $status) {
            foreach (BookedByType::cases() as $type) {
                $schedule = new Schedule;
                $schedule->forceFill([
                    'client_id' => $client->id,
                    'booked_by' => $type === BookedByType::Staff ? $sales->id : $client->id,
                    'booked_by_type' => $type,
                    'inquiry_id' => null,
                    'title' => "Visit {$status->value} by {$type->value}",
                    'arrival_at' => Carbon::now()->addDays(5)->setTime(10, 30),
                    'status' => $status,
                    'cancel_reason' => $status === ScheduleStatus::Cancelled ? 'Client asked to move it' : null,
                    'cancelled_at' => $status === ScheduleStatus::Cancelled ? Carbon::now() : null,
                    'approved_by' => in_array($status, [ScheduleStatus::Confirmed, ScheduleStatus::Served], true) ? $sales->id : null,
                    'approved_at' => in_array($status, [ScheduleStatus::Confirmed, ScheduleStatus::Served], true) ? Carbon::now() : null,
                    'assigned_to' => in_array($status, [ScheduleStatus::Confirmed, ScheduleStatus::Served], true) ? $sales->id : null,
                    'assigned_by' => in_array($status, [ScheduleStatus::Confirmed, ScheduleStatus::Served], true) ? $marketingA->id : null,
                    'assigned_at' => in_array($status, [ScheduleStatus::Confirmed, ScheduleStatus::Served], true) ? Carbon::now() : null,
                ])->save();
                $this->card($group, [
                    'type' => MessageType::ScheduleCard,
                    'schedule_id' => $schedule->id,
                    'announced_status' => $status->value,
                    'schedule_revision' => 0,
                    'announced_arrival_at' => $schedule->arrival_at,
                    'announced_reason' => $schedule->cancel_reason,
                ]);
                $schedules[] = ['status' => $status->value, 'bookedByType' => $type->value, 'title' => $schedule->title];
            }
        }

        $longTitle = substr(str_repeat('Very long visit title ', 8), 0, 150);
        $longSchedule = new Schedule;
        $longSchedule->forceFill([
            'client_id' => $client->id,
            'booked_by' => $sales->id,
            'booked_by_type' => BookedByType::Staff,
            'title' => $longTitle,
            'arrival_at' => Carbon::now()->addDays(6)->setTime(9, 0),
            'status' => ScheduleStatus::Confirmed,
            'approved_by' => $sales->id,
            'approved_at' => Carbon::now(),
            'assigned_to' => $sales->id,
            'assigned_by' => $marketingA->id,
            'assigned_at' => Carbon::now(),
        ])->save();
        $this->card($group, ['type' => MessageType::ScheduleCard, 'schedule_id' => $longSchedule->id, 'announced_status' => 'confirmed', 'schedule_revision' => 0, 'announced_arrival_at' => $longSchedule->arrival_at]);

        $this->fixtures['cards'] = [
            'conversationId' => $group->id,
            'reference' => $inquiry->reference,
            'projectName' => 'Kitchen island',
            'statusCount' => count(InquiryStatus::cases()),
            'serviceOnlyReference' => $serviceOnly->reference,
            'serviceName' => (string) $serviceOnly->service?->name,
            'quotation' => [
                'fileName' => $quotation->original_name,
                'storedName' => $quotation->stored_name,
                'sizeBytes' => $quotation->size_bytes,
            ],
            'longQuotationFileName' => $longFileName,
            'longReference' => $long->reference,
            'longProject' => $longName,
            'longScheduleTitle' => $longTitle,
            'schedules' => $schedules,
            'staffName' => $sales->name,
        ];
    }

    /** The groups and the direct chat the messaging scenarios write in. */
    private function seedChats(User $marketingA, User $marketingB, User $sales, User $directClient): void
    {
        $chat = $this->group('E2E Chat', [$marketingA, $marketingB, $sales]);
        $this->text($chat, $sales, 'Welcome to the chat');

        $scroll = $this->group('E2E Scroll', [$marketingA, $marketingB]);

        for ($m = 1; $m <= 40; $m++) {
            $this->text($scroll, $m % 2 === 0 ? $marketingA : $marketingB, "Scroll message {$m}");
        }

        $memberIds = [$marketingA->id, $directClient->id];
        sort($memberIds);
        $direct = Conversation::query()->create([
            'type' => ConversationType::Direct,
            'name' => null,
            'direct_key' => hash('sha256', implode(':', $memberIds)),
            'created_by' => $marketingA->id,
        ]);
        $this->members->upsert($direct->id, $marketingA->id, ConversationMemberRole::Member);
        $this->members->upsert($direct->id, $directClient->id, ConversationMemberRole::Member);
        $this->text($direct, $marketingA, 'Hello, direct client');

        $this->fixtures['chat'] = ['conversationId' => $chat->id, 'name' => 'E2E Chat'];
        $this->fixtures['scroll'] = ['conversationId' => $scroll->id, 'name' => 'E2E Scroll'];
        $this->fixtures['direct'] = ['conversationId' => $direct->id];
    }

    /**
     * Pending inquiries bound to their own group, for "Message the team" and the
     * status card automation, and one with no group at all. The group's sales
     * member is assigned to its inquiry, as a group's staff always are, so they
     * read it.
     */
    private function seedBoundInquiries(User $client, User $sales): void
    {
        foreach (['shown' => 'INQ-E2E-B1', 'cancel' => 'INQ-E2E-B2', 'queue' => 'INQ-E2E-B3'] as $key => $reference) {
            $inquiry = $this->inquiry($client, $reference, "Bound project {$key}");
            $group = $this->group("E2E Bound {$key}", [$sales, $client], ['inquiry_id' => $inquiry->id]);
            InquiryHandler::query()->insertOrIgnore(['inquiry_id' => $inquiry->id, 'user_id' => $sales->id, 'assigned_by' => $sales->id, 'assigned_at' => Carbon::now()]);

            $this->fixtures['bound'][$key] = ['reference' => $reference, 'conversationId' => $group->id, 'name' => $group->name];
        }

        $free = $this->inquiry($client, 'INQ-E2E-F1', 'Unbound project');
        $this->fixtures['bound']['free'] = ['reference' => $free->reference];
    }

    /**
     * @param  list<User>  $members
     * @param  array<string, mixed>  $attributes
     */
    private function group(string $name, array $members, array $attributes = []): Conversation
    {
        $group = Conversation::query()->create([
            'type' => ConversationType::Group,
            'name' => $name,
            'created_by' => $members[0]->id,
            ...$attributes,
        ]);

        foreach ($members as $index => $member) {
            $this->members->upsert($group->id, $member->id, $index === 0 ? ConversationMemberRole::Admin : ConversationMemberRole::Member);
        }

        return $group;
    }

    private function text(Conversation $conversation, User $sender, string $body): Message
    {
        $this->clock = $this->clock->addMinute();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'type' => MessageType::Text,
            'body' => $body,
            'created_at' => $this->clock,
        ]);

        $this->members->moveReadPointer($conversation->id, $sender->id, $message->id);
        $this->members->moveDeliveredPointer($conversation->id, $sender->id, $message->id);
        $conversation->forceFill(['updated_at' => $this->clock])->save();

        return $message;
    }

    /** @param  array<string, mixed>  $attributes */
    private function card(Conversation $conversation, array $attributes): Message
    {
        $this->clock = $this->clock->addMinute();

        return Message::query()->create([
            'conversation_id' => $conversation->id,
            'created_at' => $this->clock,
            ...$attributes,
        ]);
    }

    /**
     * A quotation Sales sent for the inquiry, written to the inquiry disk so
     * the card's View PDF and Download open a real file, and its card.
     */
    private function quotationCard(Conversation $conversation, Inquiry $inquiry, User $sales, string $fileName): InquiryFile
    {
        $storedName = Str::uuid()->toString().'.pdf';
        $disk = config('inquiry.disk');
        $path = "inquiries/{$inquiry->reference}/{$storedName}";
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        Storage::disk($disk)->put($path, $pdf);

        $file = $inquiry->files()->forceCreate([
            'document_type' => DocumentType::Quotation,
            'revision' => 1,
            'disk' => $disk,
            'path' => $path,
            'stored_name' => $storedName,
            'original_name' => $fileName,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'sha256' => hash('sha256', $pdf),
            'uploaded_by' => $sales->id,
        ]);

        $this->card($conversation, [
            'type' => MessageType::QuotationCard,
            'inquiry_id' => $inquiry->id,
            'inquiry_file_id' => $file->id,
            'card_snapshot' => QuotationCardSnapshot::of($file),
        ]);

        return $file;
    }

    /** A pending inquiry: one project, with no lead and no role status started. */
    private function inquiry(User $client, string $reference, string $projectName, bool $withStone = true): Inquiry
    {
        $inquiry = new Inquiry;
        $inquiry->forceFill([
            'reference' => $reference,
            'client_id' => $client->id,
            'name' => $projectName,
            'service_id' => Service::query()->where('slug', 'slab-supply-only')->value('id'),
            'delivery_method' => DeliveryMethod::Delivery,
            ...DemoPlaces::address('Makati')->toColumns(Inquiry::ADDRESS_PREFIX),
            'brand_no_preference' => true,
        ])->save();

        if (! $withStone) {
            return $inquiry;
        }

        $stoneId = Stone::query()->orderBy('id')->value('id');

        $inquiry->stones()->create([
            'stone_id' => $stoneId,
            'edge_profile_id' => EdgeProfile::query()->value('id'),
            'finish_id' => Finish::query()->value('id'),
            'quantity' => 1,
            'slab_width' => '160',
            'slab_length' => '320',
            'slab_unit' => SizeUnit::Centimetre,
            'thickness' => '20',
            'thickness_unit' => ThicknessUnit::Millimetre,
            'sort_order' => 0,
        ]);

        return $inquiry;
    }

    /**
     * An inquiry card's snapshot that shows `$status`: role statuses an inquiry
     * could have while shown at it, NULL for a stage not started yet.
     *
     * @return array<string, string|null>
     */
    private function snapshot(InquiryStatus $status): array
    {
        [$sales, $payment, $process] = match ($status) {
            InquiryStatus::Pending => [null, null, null],
            InquiryStatus::Cancelled => ['rejected', null, null],
            InquiryStatus::Closed => ['won', 'fully_paid', 'completed'],
            InquiryStatus::Negotiation, InquiryStatus::SiteVisited, InquiryStatus::OnHold => [$status->value, null, null],
            InquiryStatus::Quoted, InquiryStatus::Revision => [$status->value, null, null],
            InquiryStatus::Won => ['won', 'awaiting', 'order_received'],
            InquiryStatus::ForRefund => ['for_refund', 'partial', 'order_received'],
            default => ['won', 'awaiting', $status->value],
        };

        return [
            'announced_status' => $status->value,
            'announced_sales_status' => $sales,
            'announced_payment_status' => $payment,
            'announced_process_status' => $process,
        ];
    }
}
