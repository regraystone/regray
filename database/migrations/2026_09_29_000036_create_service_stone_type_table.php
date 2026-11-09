<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which stone types a service is made from: the Inquire page lists only the
     * stones whose primary type is linked to the chosen service, and the request
     * refuses any other. Separate from `stone_stone_type`, which holds the
     * collection filters. ServiceStoneTypeSeeder fills it.
     */
    public function up(): void
    {
        Schema::create('service_stone_type', function (Blueprint $table): void {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stone_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_id', 'stone_type_id']);
            $table->index(['stone_type_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_stone_type');
    }
};
