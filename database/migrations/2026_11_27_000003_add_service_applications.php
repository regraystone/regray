<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Natural Stone is three services (Slab Supply Only, Slab Supply and
 * Installation, Installation Only), and the two with an installation ask
 * which application the work is for: Countertop and Backsplash, Kitchen
 * Islands, Wall Cladding, Flooring, Staircase or Lavatories.
 *
 * An application stays a service row, so it keeps its own questions
 * (`service_detail_fields`) and its own stone types (`service_stone_type`):
 *
 * - `services.is_application`: offered under the services that ask one, not
 *   in the service dropdown itself.
 * - `services.has_applications`: the service asks which application, from
 *   the applications of its own service type.
 * - `inquiries.application_id`: the application an inquiry is for, null for
 *   none.
 *
 * An inquiry already sent for one of the six becomes Slab Supply and
 * Installation for that application, which is what it asked for. Its
 * answers are kept: they are keyed by question, and the application asks
 * the same ones.
 */
return new class extends Migration
{
    /** The six that were services of their own, now applications. */
    private const APPLICATIONS = ['countertop-backsplash', 'kitchen-islands', 'wall-cladding', 'flooring', 'staircase', 'lavatories'];

    /** The services that ask one. */
    private const ASKING = ['slab-supply-and-installation', 'installation-only'];

    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_application')->default(false);
            $table->boolean('has_applications')->default(false);
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->foreignId('application_id')->nullable()->after('service_id')->constrained('services')->restrictOnDelete();
            $table->index('application_id');
        });

        DB::table('services')->whereIn('slug', self::APPLICATIONS)->update(['is_application' => true]);
        DB::table('services')->whereIn('slug', self::ASKING)->update(['has_applications' => true]);

        DB::table('service_types')->where('slug', 'natural-stone-applications')->update(['name' => 'Natural Stone & Installation']);

        $supplyAndInstallation = DB::table('services')->where('slug', 'slab-supply-and-installation')->value('id');

        if ($supplyAndInstallation === null) {
            return;
        }

        DB::table('inquiries')
            ->whereIn('service_id', DB::table('services')->whereIn('slug', self::APPLICATIONS)->select('id'))
            ->update([
                'application_id' => DB::raw('service_id'),
                'service_id' => $supplyAndInstallation,
            ]);
    }

    public function down(): void
    {
        DB::table('inquiries')->whereNotNull('application_id')->update([
            'service_id' => DB::raw('application_id'),
        ]);

        DB::table('service_types')->where('slug', 'natural-stone-applications')->update(['name' => 'Natural Stone & Applications']);

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('application_id');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['is_application', 'has_applications']);
        });
    }
};
