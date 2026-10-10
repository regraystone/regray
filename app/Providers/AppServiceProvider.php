<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\ProjectCategoryRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Repositories\Eloquent\ProjectCategoryRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\StoneRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(StoneRepositoryInterface::class, StoneRepository::class);
        $this->app->bind(ProjectCategoryRepositoryInterface::class, ProjectCategoryRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->grantSuperAdminEverything();
    }

    /**
     * Let the super-admin past every permission check.
     *
     * A `before` callback runs ahead of the ability it is given, so returning
     * true settles the check there. Everything that does not return an answer
     * returns null rather than false, which is what leaves the rest of the
     * chain to Spatie's own permission check: a null here means "ask somebody
     * else", not "no".
     *
     * The super-admin is also granted every permission in the role matrix, so
     * the shared auth.permissions prop still describes what it may see rather
     * than coming back empty. This bypass is what stops a permission being
     * forgotten from locking the super-admin out of its own page.
     */
    protected function grantSuperAdminEverything(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->hasRole(Role::superAdmin()) ?: null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
