<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The line under "How would you like to get it?" says how long a delivery
 * takes, and the Inquire page shows it only once Delivery is picked: the
 * furniture services' "Ready-made pieces are delivered within 5 to 7 days.
 * Custom designs take longer; we will confirm the timeline in your
 * quotation.", which moves there from under the date. Slab Supply Only gets
 * none, as it has no ready-made or custom piece.
 *
 * The Metro Manila fee line goes. Only a line still as it was seeded is
 * changed: one an admin has rewritten on the service form stays.
 */
return new class extends Migration
{
    private const FEE = 'Delivery within Metro Manila is charged at a standard flat rate. For addresses outside Metro Manila, the fee depends on the location and is included in your quotation.';

    private const HOW_LONG = 'Ready-made pieces are delivered within 5 to 7 days. Custom designs take longer; we will confirm the timeline in your quotation.';

    private const FURNITURE = ['center-tables', 'side-tables', 'dining-tables', 'other-customized-furniture'];

    public function up(): void
    {
        DB::table('services')->whereIn('slug', self::FURNITURE)->where('delivery_note', self::FEE)->update(['delivery_note' => self::HOW_LONG]);
        DB::table('services')->whereNotIn('slug', self::FURNITURE)->where('delivery_note', self::FEE)->update(['delivery_note' => null]);
        // Said under the delivery method now, so not twice.
        DB::table('services')->where('schedule_note', self::HOW_LONG)->update(['schedule_note' => null]);
    }

    public function down(): void
    {
        DB::table('services')->whereIn('slug', self::FURNITURE)->where('delivery_note', self::HOW_LONG)->update(['delivery_note' => self::FEE, 'schedule_note' => self::HOW_LONG]);
        DB::table('services')->where('slug', 'slab-supply-only')->whereNull('delivery_note')->update(['delivery_note' => self::FEE]);
    }
};
