<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the Inquire page says around a service's questions, kept where the
 * admin can change it.
 *
 * `services.delivery_note`: a line under "How would you like to get it?",
 * such as the delivery fee within Metro Manila and outside it.
 * `services.schedule_note`: a line under "When do you need it?", such as how
 * long a ready-made piece or a custom design takes.
 * `services.lead_days`: the fewest days from today the client may ask for it
 * (null or 0: today). An edit may keep the date it already has.
 *
 * `detail_fields.custom_label` and `custom_hint`: what a free entry's Other
 * reads ("Custom size") and the line under the box it opens ("Enter your
 * desired size."). None reads Other, with no line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('delivery_note', 500)->nullable();
            $table->string('schedule_note', 500)->nullable();
            $table->unsignedSmallInteger('lead_days')->nullable();
        });

        Schema::table('detail_fields', function (Blueprint $table) {
            $table->string('custom_label', 50)->nullable();
            $table->string('custom_hint', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('detail_fields', function (Blueprint $table) {
            $table->dropColumn(['custom_label', 'custom_hint']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['delivery_note', 'schedule_note', 'lead_days']);
        });
    }
};
