<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A project's quotations, one row per revision.
 *
 * A pending quotation is with the client. It becomes `closeable` when they
 * accept it and `closed` when it is settled, or `revision` when a newer
 * revision replaces it, or `rejected`. Only one revision is live at a time:
 * pending, closeable or closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_project_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('revision_no');
            $table->string('status', 20);
            $table->decimal('total_amount', 12, 2);
            $table->timestampTz('issued_at');
            $table->timestampTz('responded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['inquiry_project_id', 'revision_no']);
        });

        DB::statement('ALTER TABLE quotations ADD CONSTRAINT chk_quotations_revision_no CHECK (revision_no >= 1)');
        DB::statement("ALTER TABLE quotations ADD CONSTRAINT chk_quotations_status CHECK (status IN ('pending','closed','revision','closeable','rejected'))");
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT chk_quotations_total_amount CHECK (total_amount > 0)');
        DB::statement("CREATE UNIQUE INDEX idx_quotations_one_live ON quotations (inquiry_project_id) WHERE status IN ('pending','closeable','closed')");
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
