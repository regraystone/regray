import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { APP_ENV, DB_DATABASE } from './env';
import { ROOT, artisan, startProcess, stopProcess } from './stack';

const ALLOWED_ENVIRONMENTS = ['local', 'testing'];

/**
 * Refuse anything that could touch a real database, then reset and seed the
 * E2E database and start the stack against it.
 */
export default async function globalSetup(): Promise<void> {
    if (!ALLOWED_ENVIRONMENTS.includes(APP_ENV)) {
        throw new Error(
            `Refusing to run: E2E_APP_ENV is "${APP_ENV}", not local or testing.`,
        );
    }

    // A cached config ignores the environment, so the database override below
    // would silently be the cached one.
    if (existsSync(join(ROOT, 'bootstrap/cache/config.php'))) {
        throw new Error(
            'Refusing to run: the config is cached. Run `php artisan config:clear` first.',
        );
    }

    const environment = artisan('env').trim();

    if (
        !ALLOWED_ENVIRONMENTS.some((name) => environment.includes(`[${name}]`))
    ) {
        throw new Error(
            `Refusing to run: the application reports "${environment}".`,
        );
    }

    if (DB_DATABASE === 'regray-studio' || /prod/i.test(DB_DATABASE)) {
        throw new Error(
            `Refusing to run against the database "${DB_DATABASE}".`,
        );
    }

    artisan(
        'tinker',
        '--execute',
        `config(['database.connections.pgsql.database' => 'postgres']); DB::purge('pgsql');` +
            ` if (! DB::selectOne('select 1 as found from pg_database where datname = ?', ['${DB_DATABASE}'])) {` +
            ` DB::statement('create database "${DB_DATABASE}"'); }`,
    );

    artisan('migrate:fresh', '--seed', '--force');
    artisan('db:seed', '--class=E2EMessageSeeder', '--force');

    for (const name of ['app', 'reverb', 'queue'] as const) {
        await stopProcess(name);
        await startProcess(name);
    }
}
