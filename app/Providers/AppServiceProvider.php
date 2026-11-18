<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Repositories\Contracts\ClientInformationRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\FurnitureRepositoryInterface;
use App\Repositories\Contracts\InquiryCatalogRepositoryInterface;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\ProjectCategoryRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\SavedFurnitureRepositoryInterface;
use App\Repositories\Contracts\SavedStoneRepositoryInterface;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\BrandRepository;
use App\Repositories\Eloquent\EloquentAuditLogRepository;
use App\Repositories\Eloquent\EloquentClientInformationRepository;
use App\Repositories\Eloquent\EloquentClientRepository;
use App\Repositories\Eloquent\EloquentConversationMemberRepository;
use App\Repositories\Eloquent\EloquentConversationRepository;
use App\Repositories\Eloquent\EloquentInquiryCatalogRepository;
use App\Repositories\Eloquent\EloquentInquiryRepository;
use App\Repositories\Eloquent\EloquentLeadRepository;
use App\Repositories\Eloquent\EloquentMessageRepository;
use App\Repositories\Eloquent\EloquentScheduleRepository;
use App\Repositories\Eloquent\FurnitureRepository;
use App\Repositories\Eloquent\ProjectCategoryRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\SavedFurnitureRepository;
use App\Repositories\Eloquent\SavedStoneRepository;
use App\Repositories\Eloquent\StoneRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\BestSellerService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Horizon\Horizon;
use Laravel\Pulse\Facades\Pulse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The named rate limiters routes ask for with `throttle:{name}`. Each
     * one's requests per minute is config/rate-limits.php's `per_minute.{name}`.
     *
     * @var list<string>
     */
    private const RATE_LIMITERS = [
        'messages',
        'message-receipts',
        'message-assignment',
        'message-people',
        'message-attachments',
        'notifications',
        'inquiry-submit',
        'inquiry-edit',
        'client-information-save',
        'inquiry-files',
        'booking',
        'saved-stone',
        'group-create',
        'heavy-reads',
        'register',
        'social-login',
        'password-reset',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(StoneRepositoryInterface::class, StoneRepository::class);
        $this->app->bind(ProjectCategoryRepositoryInterface::class, ProjectCategoryRepository::class);
        $this->app->bind(BrandRepositoryInterface::class, BrandRepository::class);
        $this->app->bind(FurnitureRepositoryInterface::class, FurnitureRepository::class);
        $this->app->bind(SavedStoneRepositoryInterface::class, SavedStoneRepository::class);
        $this->app->bind(SavedFurnitureRepositoryInterface::class, SavedFurnitureRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(InquiryRepositoryInterface::class, EloquentInquiryRepository::class);
        $this->app->bind(LeadRepositoryInterface::class, EloquentLeadRepository::class);
        $this->app->bind(ClientRepositoryInterface::class, EloquentClientRepository::class);
        $this->app->bind(ClientInformationRepositoryInterface::class, EloquentClientInformationRepository::class);
        $this->app->bind(InquiryCatalogRepositoryInterface::class, EloquentInquiryCatalogRepository::class);
        $this->app->bind(ConversationRepositoryInterface::class, EloquentConversationRepository::class);
        $this->app->bind(ConversationMemberRepositoryInterface::class, EloquentConversationMemberRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, EloquentMessageRepository::class);
        $this->app->bind(ScheduleRepositoryInterface::class, EloquentScheduleRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, EloquentAuditLogRepository::class);

        // One best-seller ranking per request, however many stones ask.
        $this->app->scoped(BestSellerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->grantSuperAdminEverything();
        $this->authorizeDashboards();
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
     * Who may open the Pulse (/pulse) and Horizon (/horizon) dashboards: the
     * owner and the admin, on every environment.
     *
     * Both packages let anyone in on a local environment by default; these
     * gates replace that. The admin is also let through by the super-admin
     * bypass above. Pulse shows each user by name and email with no avatar,
     * since its default avatar asks Gravatar about the user's email.
     */
    protected function authorizeDashboards(): void
    {
        $isOwnerOrAdmin = fn (User $user): bool => $user->hasAnyRole([Role::Owner->value, Role::Admin->value]);

        Gate::define('viewPulse', $isOwnerOrAdmin);
        Gate::define('viewHorizon', $isOwnerOrAdmin);

        Horizon::auth(fn (Request $request): bool => (bool) $request->user()?->can('viewHorizon'));

        Pulse::user(fn (User $user): array => [
            'name' => $user->name,
            'extra' => $user->email,
            'avatar' => null,
        ]);
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

        /*
         * An N+1 throws everywhere but production, so the test suite and local
         * use catch it. Production stays quiet and keeps serving.
         */
        Model::preventLazyLoading(! app()->isProduction());

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
     * The named limits, each counted per signed-in user and per IP for a
     * guest, a minute at a time. The numbers are in config/rate-limits.php,
     * read on every request so the environment can change them.
     *
     * The Inbox page's:
     * - `messages`, sending: generous enough for a fast typist, low enough
     *   that a stuck retry loop cannot flood a thread.
     * - `message-receipts`, read and delivered pointers: the page sends one
     *   per incoming message and per thread opened, so this is the highest,
     *   and only a loop reaches it.
     * - `message-assignment`, claiming a Regray Team thread: a person does this
     *   a few times an hour.
     * - `notifications`, the bell: a page as the list scrolls, opening one,
     *   marking all read.
     * - `message-people`, the people search behind "New group": one request
     *   per pause in typing.
     * - `message-attachments`, opening a file: a thread shows every photo in
     *   it at once, so this only stops someone walking through the files.
     *
     * The rest:
     * - `inquiry-submit` and `inquiry-edit`: sending, editing and cancelling
     *   an inquiry, each of which writes files and visits. Both inquiry doors
     *   share one count.
     * - `inquiry-files`: previewing and downloading an inquiry's files, each
     *   preview a HEAD and a GET; only a script walking the files reaches it.
     * - `booking`: Book an appointment.
     * - `saved-stone`: the heart on a stone card. A person taps through a
     *   feed; only a script reaches this.
     * - `group-create`: New group on the Inbox page.
     * - `heavy-reads`: the Home and Saved feeds, where every filter change,
     *   search and page of infinite scroll is a request.
     * - `register` and `password-reset`: Fortify's guest forms, per IP.
     * - `social-login`: signing in, signing up and connecting with Google.
     */
    protected function configureRateLimits(): void
    {
        $perUser = fn (Request $request): string => (string) ($request->user()->id ?? $request->ip());

        foreach (self::RATE_LIMITERS as $name) {
            RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinute(config()->integer("rate-limits.per_minute.{$name}"))->by($perUser($request)));
        }

        $this->throttleFortifyForms();
    }

    /**
     * Fortify registers its register and password-reset routes with no limit
     * and has no setting for one, so the throttle goes onto the routes once
     * they exist. `route:cache` runs this too, so a cached route list already
     * carries it and is left alone.
     */
    protected function throttleFortifyForms(): void
    {
        $this->app->booted(function (): void {
            if ($this->app->routesAreCached()) {
                return;
            }

            $routes = Route::getRoutes();
            $routes->refreshNameLookups();

            foreach (['register.store' => 'register', 'password.email' => 'password-reset', 'password.update' => 'password-reset'] as $route => $limiter) {
                $routes->getByName($route)?->middleware("throttle:{$limiter}");
            }
        });
    }
}
