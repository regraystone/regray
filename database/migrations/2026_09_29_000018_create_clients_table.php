<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every client the studio works for, with or without a Regray account.
 *
 * - `kind`: `platform` when the client has an account (`user_id` set), and
 *   `direct` (a walk-in, shown as "Direct (walk-in)") when they have none.
 *   That pairing is the app's rule, not a CHECK.
 * - `user_id`: the client's account, unique, so an account has one client
 *   record at most. Two records are never merged into one.
 * - A direct client's phone, Viber, WhatsApp and email are rows of
 *   `client_contacts`; a platform client's are on their account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('full_name', 150);
            $table->string('company', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('kind');
        });

        DB::statement("ALTER TABLE clients ADD CONSTRAINT chk_clients_kind CHECK (kind IN ('platform','direct'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
