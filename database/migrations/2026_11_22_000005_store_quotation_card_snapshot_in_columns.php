<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A quotation card's snapshot moves from one JSON column (`card_snapshot`) to
 * a typed column for each part, as a schedule card's already is
 * (`schedule_revision`, `announced_arrival_at`, `announced_reason`). Each
 * card's values are copied across, then the JSON column goes.
 *
 * - `card_file_name`, `card_stored_name`, `card_size_bytes`, `card_revision`:
 *   the document as it was posted (its own name, the name it is served by,
 *   its size, its revision, if a quotation has one);
 * - `card_material_count`, `card_inquiry_name`, `card_reference`: the inquiry
 *   as it was (its stones, counted once each, its name and reference).
 *
 * The type CHECK keeps its rule: a quotation card has every part but the
 * revision, and any other type has none.
 */
return new class extends Migration
{
    /** @var array<string, string> Each column, by the snapshot key it was. */
    private const COLUMNS = [
        'fileName' => 'card_file_name',
        'storedName' => 'card_stored_name',
        'sizeBytes' => 'card_size_bytes',
        'revision' => 'card_revision',
        'materialCount' => 'card_material_count',
        'inquiryName' => 'card_inquiry_name',
        'reference' => 'card_reference',
    ];

    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('card_file_name', 255)->nullable()->after('announced_reason');
            $table->string('card_stored_name', 100)->nullable()->after('card_file_name');
            $table->bigInteger('card_size_bytes')->nullable()->after('card_stored_name');
            $table->smallInteger('card_revision')->nullable()->after('card_size_bytes');
            $table->integer('card_material_count')->nullable()->after('card_revision');
            $table->string('card_inquiry_name', 100)->nullable()->after('card_material_count');
            $table->string('card_reference', 30)->nullable()->after('card_inquiry_name');
        });

        DB::table('messages')->whereNotNull('card_snapshot')->orderBy('id')->each(function (object $message): void {
            /** @var array<string, mixed> $snapshot */
            $snapshot = json_decode((string) $message->card_snapshot, true, flags: JSON_THROW_ON_ERROR);
            $columns = [];

            foreach (self::COLUMNS as $key => $column) {
                $columns[$column] = $snapshot[$key] ?? null;
            }

            DB::table('messages')->where('id', $message->id)->update($columns);
        });

        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS chk_messages_type');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('card_snapshot');
        });

        DB::statement(self::typeCheck(inColumns: true));
        DB::statement('ALTER TABLE messages ADD CONSTRAINT chk_messages_card_counts CHECK (card_size_bytes >= 0 AND card_material_count >= 0 AND card_revision >= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS chk_messages_card_counts');
        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS chk_messages_type');

        Schema::table('messages', function (Blueprint $table) {
            $table->jsonb('card_snapshot')->nullable()->after('announced_reason');
        });

        DB::table('messages')->whereNotNull('card_file_name')->orderBy('id')->each(function (object $message): void {
            $snapshot = [];

            foreach (self::COLUMNS as $key => $column) {
                $snapshot[$key] = $message->{$column};
            }

            DB::table('messages')->where('id', $message->id)->update(['card_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(array_values(self::COLUMNS));
        });

        DB::statement(self::typeCheck(inColumns: false));
    }

    /** The type CHECK, with the quotation card's snapshot as columns or as the one JSON column. */
    private static function typeCheck(bool $inColumns): string
    {
        $noSnapshot = 'announced_sales_status IS NULL AND announced_payment_status IS NULL AND announced_process_status IS NULL';
        $noScheduleSnapshot = 'schedule_revision IS NULL AND announced_arrival_at IS NULL AND announced_reason IS NULL';
        $hasCard = $inColumns
            ? 'card_file_name IS NOT NULL AND card_stored_name IS NOT NULL AND card_size_bytes IS NOT NULL AND card_material_count IS NOT NULL AND card_inquiry_name IS NOT NULL AND card_reference IS NOT NULL'
            : 'card_snapshot IS NOT NULL';
        $noCard = $inColumns
            ? 'card_file_name IS NULL AND card_stored_name IS NULL AND card_size_bytes IS NULL AND card_revision IS NULL AND card_material_count IS NULL AND card_inquiry_name IS NULL AND card_reference IS NULL'
            : 'card_snapshot IS NULL';
        $noQuotation = "AND inquiry_file_id IS NULL AND {$noCard}";

        return "
            ALTER TABLE messages ADD CONSTRAINT chk_messages_type CHECK (
                (type = 'text' AND body IS NOT NULL AND system_event IS NULL AND system_user_id IS NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL
                    AND {$noSnapshot} AND {$noScheduleSnapshot} {$noQuotation})
                OR (type = 'inquiry_card' AND body IS NULL AND inquiry_id IS NOT NULL AND schedule_id IS NULL AND system_event IS NULL AND system_user_id IS NULL
                    AND announced_status IS NOT NULL
                    AND announced_status IN ('pending','cancelled','closed','negotiation','site_visited','on_hold','quoted','revision','won','order_received','measure','sourcing','production','quality_check','ready','dispatched','installation','delayed','for_refund','refunded')
                    AND {$noScheduleSnapshot} {$noQuotation})
                OR (type = 'schedule_card' AND body IS NULL AND schedule_id IS NOT NULL AND announced_status IS NOT NULL AND inquiry_id IS NULL AND system_event IS NULL AND system_user_id IS NULL
                    AND {$noSnapshot}
                    AND announced_status IN ('pending','confirmed','rescheduled','served','cancelled','expired')
                    AND schedule_revision IS NOT NULL AND announced_arrival_at IS NOT NULL {$noQuotation})
                OR (type = 'system' AND body IS NULL AND system_event IS NOT NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL
                    AND {$noSnapshot} AND {$noScheduleSnapshot} {$noQuotation})
                OR (type = 'quotation_card' AND body IS NULL AND inquiry_id IS NOT NULL AND inquiry_file_id IS NOT NULL AND {$hasCard}
                    AND schedule_id IS NULL AND system_event IS NULL AND system_user_id IS NULL AND announced_status IS NULL
                    AND {$noSnapshot} AND {$noScheduleSnapshot})
            )
        ";
    }
};
