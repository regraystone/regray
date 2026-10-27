<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What is owed on a project's accepted quotation, and how much of it is paid.
 * One record per quotation; the deposits and refunds behind `amount_paid` are
 * in `payment_transactions`.
 *
 * The status has to agree with the amounts: awaiting has nothing paid, partial
 * has some, fully_paid has all of it. A refunded payment keeps what was paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status', 20);
            $table->timestampTz('status_changed_at');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('inquiry_project_id');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_status CHECK (status IN ('awaiting','partial','fully_paid','refunded'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount_due CHECK (amount_due > 0)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount_paid CHECK (amount_paid >= 0 AND amount_paid <= amount_due)');
        DB::statement("
            ALTER TABLE payments ADD CONSTRAINT chk_payments_status_amounts CHECK (
                (status <> 'awaiting' OR amount_paid = 0)
                AND (status <> 'partial' OR (amount_paid > 0 AND amount_paid < amount_due))
                AND (status <> 'fully_paid' OR amount_paid = amount_due)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
