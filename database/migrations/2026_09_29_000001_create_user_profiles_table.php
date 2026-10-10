<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->cascadeOnDelete();
            $table->string('phone', 30);
            $table->string('company', 150)->nullable();
            $table->string('address', 255);
            $table->string('avatar_path', 255)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
