<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\InquiryCatalogRepositoryInterface;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\ProjectCategoryRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\SavedStoneRepositoryInterface;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentConversationMemberRepository;
use App\Repositories\Eloquent\EloquentConversationRepository;
use App\Repositories\Eloquent\EloquentInquiryCatalogRepository;
use App\Repositories\Eloquent\EloquentInquiryRepository;
use App\Repositories\Eloquent\EloquentMessageRepository;
use App\Repositories\Eloquent\ProjectCategoryRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\SavedStoneRepository;
use App\Repositories\Eloquent\StoneRepository;
use App\Repositories\Eloquent\UserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(SavedStoneRepositoryInterface::class, SavedStoneRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(InquiryRepositoryInterface::class, EloquentInquiryRepository::class);
        $this->app->bind(InquiryCatalogRepositoryInterface::class, EloquentInquiryCatalogRepository::class);
        $this->app->bind(ConversationRepositoryInterface::class, EloquentConversationRepository::class);
        $this->app->bind(ConversationMemberRepositoryInterface::class, EloquentConversationMemberRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, EloquentMessageRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->grantSuperAdminEverything();
        $this->configureRateLimits();
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

    /**
     * The Message page's limits, each counted per user (per IP for a guest,
     * who is refused anyway):
     * - `messages`, sending: generous enough for a fast typist, low enough
     *   that a stuck retry loop cannot flood a thread.
     * - `message-receipts`, read and delivered pointers: the page sends one
     *   per incoming message and per thread opened, so this is the highest,
     *   and only a loop reaches it.
     * - `message-assignment`, claim, release and reassign: a person does these
     *   a few times an hour.
     * - `message-people`, the people search behind "New group": one request
     *   per pause in typing.
     */
    protected function configureRateLimits(): void
    {
        $perUser = fn (Request $request): string => (string) ($request->user()->id ?? $request->ip());

        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(60)->by($perUser($request)));
        RateLimiter::for('message-receipts', fn (Request $request) => Limit::perMinute(120)->by($perUser($request)));
        RateLimiter::for('message-assignment', fn (Request $request) => Limit::perMinute(10)->by($perUser($request)));
        RateLimiter::for('message-people', fn (Request $request) => Limit::perMinute(60)->by($perUser($request)));
    }
}
