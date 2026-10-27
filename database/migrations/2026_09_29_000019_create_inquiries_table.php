<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS inquiry_reference_seq START 1');

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->timestampTz('status_changed_at');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('client_id');
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_status CHECK (status IN ('pending','ongoing','closed','lost'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inquiries DROP CONSTRAINT IF EXISTS chk_inquiries_status');
        Schema::dropIfExists('inquiries');
        DB::statement('DROP SEQUENCE IF EXISTS inquiry_reference_seq');
    }
};
