<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the admin's Accounts page manages on a user: whether they may sign in
 * (`is_active`, with when that last changed to off), whether the account is
 * archived (gone from the list, and unable to sign in, for good), and the
 * temporary password a new staff account starts with, which they must change
 * on their first sign-in.
 *
 * An archived account keeps its row, its email and its history: the email
 * stays unique, so it cannot be reused by a new account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestampTz('password_changed_at')->nullable();

            // The Accounts list: the unarchived, by status, newest first.
            $table->index(['archived_at', 'is_active', 'created_at'], 'users_accounts_list_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_accounts_list_index');
            $table->dropColumn(['is_active', 'deactivated_at', 'archived_at', 'must_change_password', 'password_changed_at']);
        });
    }
};
