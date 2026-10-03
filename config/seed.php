<?php

return [
    /*
     * Whether the seeders may create the demo accounts.
     *
     * DatabaseSeeder creates a user per role so a fresh local install has
     * somebody to sign in as. Those accounts are not wanted anywhere else, so
     * the check lives here rather than in the seeder: reading env() from a
     * seeder returns null once the config is cached, which is exactly the
     * environment where the accounts matter least and the guard has to hold.
     *
     * Defaults to on when APP_ENV is local. An empty value counts as unset, so
     * listing the key in .env.example with nothing after it does not quietly
     * switch the demo accounts off on every fresh clone.
     */
    'demo_accounts' => env('SEED_DEMO_ACCOUNTS') === null
        ? env('APP_ENV') === 'local'
        : filter_var(env('SEED_DEMO_ACCOUNTS'), FILTER_VALIDATE_BOOLEAN),

    /*
     * The password those accounts get, or null to leave the factory's own.
     *
     * Set SEED_PASSWORD to pick one; leaving it unset uses UserFactory's
     * default, which is fine locally and is never committed.
     */
    'password' => env('SEED_PASSWORD') ?: null,
];
