<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * An outside account a user signs in with, such as their Google account. One
 * row per provider account: a Google account belongs to one user, and a user
 * has at most one of each provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            // The provider's own id for the account: it never changes, unlike the email.
            $table->string('provider_user_id', 255);
            $table->string('email', 255)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
