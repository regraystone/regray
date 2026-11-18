<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The client's own saved "how would you like to get it?" answer, and the copy
 * sent with an inquiry.
 *
 * `client_information` is one row per account, edited from the Profile hub and
 * used as the starting point for the next quotation request. It holds only the
 * delivery choice and, when delivery was chosen, the city or municipality. No
 * contact or billing details are kept here.
 *
 * `client_information_snapshots` is what a client actually sent with one
 * inquiry, copied once when the inquiry is written. It is keyed to the inquiry
 * rather than the account, so it stays with retained inquiry history; only the
 * mutable saved row follows the account when it is deleted. The inquiry also
 * holds its own delivery answer, but the snapshot keeps the one the client
 * originally chose even if that project is later edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_information', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('delivery_method', 16);
            $table->string('location', 255)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });

        DB::statement("ALTER TABLE client_information ADD CONSTRAINT chk_client_information_delivery_method CHECK (delivery_method IN ('delivery', 'pickup'))");
        DB::statement("ALTER TABLE client_information ADD CONSTRAINT chk_client_information_location CHECK ((delivery_method = 'pickup' AND location IS NULL) OR (delivery_method = 'delivery' AND location IS NOT NULL AND char_length(location) > 0))");

        Schema::create('client_information_snapshots', function (Blueprint $table): void {
            $table->foreignId('inquiry_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('delivery_method', 16);
            $table->string('location', 255)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });

        DB::statement("ALTER TABLE client_information_snapshots ADD CONSTRAINT chk_client_information_snapshots_delivery_method CHECK (delivery_method IN ('delivery', 'pickup'))");
        DB::statement("ALTER TABLE client_information_snapshots ADD CONSTRAINT chk_client_information_snapshots_location CHECK ((delivery_method = 'pickup' AND location IS NULL) OR (delivery_method = 'delivery' AND location IS NOT NULL AND char_length(location) > 0))");
    }

    public function down(): void
    {
        Schema::dropIfExists('client_information_snapshots');
        Schema::dropIfExists('client_information');
    }
};
