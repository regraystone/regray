<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How to reach a client who has no account: their phone number and, when
 * they gave one, their email address. `value` is stored normalised by the app (an
 * email lowercased, a number without spaces or dashes), and a kind and value
 * belong to one client only (`idx_client_contacts_once`): the duplicate check
 * offers that client rather than creating a second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('value', 255);
            $table->timestampTz('created_at');

            $table->index('client_id');
            $table->unique(['kind', 'value'], 'idx_client_contacts_once');
        });

        DB::statement("ALTER TABLE client_contacts ADD CONSTRAINT chk_client_contacts_kind CHECK (kind IN ('phone','email'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
