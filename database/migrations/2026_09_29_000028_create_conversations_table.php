<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('name', 150)->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->foreignId('inquiry_id')->nullable()->unique()->setOnDelete('set null');
            $table->char('direct_key', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->setOnDelete('set null');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });

        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_type CHECK (type IN ('direct','group'))");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_direct_key CHECK ((type = 'direct') = (direct_key IS NOT NULL))");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_inquiry CHECK (inquiry_id IS NULL OR type = 'group')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS chk_conversations_type');
        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS chk_conversations_direct_key');
        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS chk_conversations_inquiry');
        Schema::dropIfExists('conversations');
    }
};
