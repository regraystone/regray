<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
