<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Projects are now Showcases, in the database too:
 *
 * - The tables: projects, project_images, project_stone, project_categories
 *   and category_project become showcases, showcase_images, showcase_stone,
 *   showcase_categories and category_showcase; `project_id` becomes
 *   `showcase_id` and `project_category_id` `showcase_category_id`. Their
 *   indexes, constraints and id sequences are renamed to match, read from
 *   Postgres rather than listed, so none is missed.
 * - The permissions: projects.view / create / update / archive,
 *   home-projects.view and saved-projects.view become showcases.* and
 *   home- / saved-showcases.view. The rows are renamed in place, so every role
 *   keeps what it had.
 * - The audit log: an entry about a project names the model by class
 *   (App\Models\Project), so it now names App\Models\Showcase.
 *
 * The images stay where they are, under projects/ on the public disk, and so
 * do the paths stored for them.
 */
return new class extends Migration
{
    /** @var array<string, string> Each table, by its old name. */
    private const TABLES = [
        'projects' => 'showcases',
        'project_images' => 'showcase_images',
        'project_stone' => 'showcase_stone',
        'project_categories' => 'showcase_categories',
        'category_project' => 'category_showcase',
    ];

    /** @var array<string, array<string, string>> Each table's renamed columns, by the table's new name. */
    private const COLUMNS = [
        'showcase_images' => ['project_id' => 'showcase_id'],
        'showcase_stone' => ['project_id' => 'showcase_id'],
        'category_showcase' => ['project_id' => 'showcase_id', 'project_category_id' => 'showcase_category_id'],
    ];

    /** @var array<string, string> */
    private const PERMISSIONS = [
        'projects.view' => 'showcases.view',
        'projects.create' => 'showcases.create',
        'projects.update' => 'showcases.update',
        'projects.archive' => 'showcases.archive',
        'home-projects.view' => 'home-showcases.view',
        'saved-projects.view' => 'saved-showcases.view',
    ];

    /** @var array<string, string> */
    private const MODELS = [
        'App\\Models\\Project' => 'App\\Models\\Showcase',
        'App\\Models\\ProjectImage' => 'App\\Models\\ShowcaseImage',
        'App\\Models\\ProjectCategory' => 'App\\Models\\ShowcaseCategory',
    ];

    public function up(): void
    {
        $this->rename(self::TABLES, self::COLUMNS, 'project', 'showcase', self::PERMISSIONS, self::MODELS);
    }

    public function down(): void
    {
        $columns = [];

        foreach (self::COLUMNS as $table => $renames) {
            $columns[array_search($table, self::TABLES, true)] = array_flip($renames);
        }

        $this->rename(array_flip(self::TABLES), $columns, 'showcase', 'project', array_flip(self::PERMISSIONS), array_flip(self::MODELS));
    }

    /**
     * @param  array<string, string>  $tables
     * @param  array<string, array<string, string>>  $columns
     * @param  array<string, string>  $permissions
     * @param  array<string, string>  $models
     */
    private function rename(array $tables, array $columns, string $from, string $to, array $permissions, array $models): void
    {
        foreach ($tables as $old => $new) {
            Schema::rename($old, $new);
        }

        foreach ($columns as $table => $renames) {
            foreach ($renames as $old => $new) {
                Schema::table($table, fn ($blueprint) => $blueprint->renameColumn($old, $new));
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            $this->renamePostgresObjects(array_values($tables), $from, $to);
        }

        foreach ($permissions as $old => $new) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }

        foreach ($models as $old => $new) {
            DB::table('audit_logs')->where('auditable_type', $old)->update(['auditable_type' => $new]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The tables' constraints (a primary key or unique constraint renames its
     * index with it), their other indexes and their id sequences, each with
     * `$from` in its name, get `$to` in its place.
     *
     * @param  list<string>  $tables
     */
    private function renamePostgresObjects(array $tables, string $from, string $to): void
    {
        $quoted = implode(', ', array_map(fn (string $table): string => DB::getPdo()->quote($table), $tables));
        $renamed = fn (string $name): string => str_replace($from, $to, $name);

        $constraints = DB::select("
            SELECT c.conname AS name, t.relname AS table_name
            FROM pg_constraint c
            JOIN pg_class t ON t.oid = c.conrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE n.nspname = current_schema() AND t.relname IN ({$quoted}) AND c.conname LIKE ?
        ", ["%{$from}%"]);

        foreach ($constraints as $constraint) {
            DB::statement(sprintf(
                'ALTER TABLE "%s" RENAME CONSTRAINT "%s" TO "%s"',
                $constraint->table_name,
                $constraint->name,
                $renamed($constraint->name),
            ));
        }

        $indexes = DB::select("
            SELECT i.relname AS name
            FROM pg_index x
            JOIN pg_class i ON i.oid = x.indexrelid
            JOIN pg_class t ON t.oid = x.indrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE n.nspname = current_schema() AND t.relname IN ({$quoted}) AND i.relname LIKE ?
                AND NOT EXISTS (SELECT 1 FROM pg_constraint c WHERE c.conindid = x.indexrelid)
        ", ["%{$from}%"]);

        foreach ($indexes as $index) {
            DB::statement(sprintf('ALTER INDEX "%s" RENAME TO "%s"', $index->name, $renamed($index->name)));
        }

        foreach ($tables as $table) {
            // The two pivot tables have no id.
            if (! Schema::hasColumn($table, 'id')) {
                continue;
            }

            $sequence = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS name', [$table, 'id'])?->name;

            if ($sequence === null) {
                continue;
            }

            // Schema-qualified, perhaps quoted: rename the bare name only.
            $bare = trim(str_contains($sequence, '.') ? substr($sequence, strrpos($sequence, '.') + 1) : $sequence, '"');

            if (str_contains($bare, $from)) {
                DB::statement(sprintf('ALTER SEQUENCE "%s" RENAME TO "%s"', $bare, $renamed($bare)));
            }
        }
    }
};
