<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The files an inquiry carries, by what they are:
 *
 * - `client_attachment`: what the client sent with the inquiry;
 * - `quotation`: a quotation sales issued, with the `revision` it was;
 * - `job_order`: the job order sales uploads once the client has approved;
 * - `receipt`: a receipt accounting uploads when a payment comes in.
 *
 * Who may upload which is the app's rule (App\Policies\InquiryFileUploads),
 * not the database's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->smallInteger('revision')->nullable();
            $table->string('disk', 30);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->char('sha256', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
            $table->softDeletesTz();

            $table->index(['inquiry_id', 'document_type']);
        });

        DB::statement("ALTER TABLE inquiry_files ADD CONSTRAINT chk_inquiry_files_document_type CHECK (document_type IN ('client_attachment','quotation','receipt','job_order'))");
        DB::statement('ALTER TABLE inquiry_files ADD CONSTRAINT chk_inquiry_files_revision CHECK (revision >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_files');
    }
};
