<?php

namespace App\Console\Commands;

use App\Actions\Admin\CreateAdminUserAction;
use App\Enums\Role;
use Illuminate\Console\Command;
use RuntimeException;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:create-admin
                            {email : The email address of the new admin user}
                            {--name=Admin : The name of the new admin user}
                            {--password= : The password for the new admin user}';

    /**
     * The console command description.
     */
    protected $description = 'Create an admin user for production bootstrap.';

    public function __construct(
        public readonly CreateAdminUserAction $action
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->option('name');
        $password = $this->option('password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        if ($password === null || $password === '') {
            $this->error('Password is required. Use --password= to provide it.');

            return self::FAILURE;
        }

        try {
            $user = $this->action->execute($name, $email, $password);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $role = $user->primaryRole()->value ?? Role::Admin->value;
        $this->info("Created admin user: {$user->email} ({$role})");

        return self::SUCCESS;
    }
}
