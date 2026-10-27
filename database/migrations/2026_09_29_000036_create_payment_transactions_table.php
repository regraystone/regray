<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger behind a payment: each deposit and each refund, as received.
 * A payment's `amount_paid` is the sum of its `payment` rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 60)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('received_at');
            $table->timestampTz('created_at');

            $table->index(['payment_id', 'received_at']);
        });

        DB::statement("ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transactions_kind CHECK (kind IN ('payment','refund'))");
        DB::statement('ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transactions_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
