<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Recommended" was set by hand; a stone is now a best seller by how often
     * it is in completed inquiries (App\Services\BestSellerService).
     */
    public function up(): void
    {
        Schema::table('stones', function (Blueprint $table) {
            $table->dropColumn('recommended');
        });
    }

    /**
     * Brings the column back with every stone unrecommended: the hand-set
     * values are gone once up() has run.
     */
    public function down(): void
    {
        Schema::table('stones', function (Blueprint $table) {
            $table->boolean('recommended')->default(false);
        });
    }
};
