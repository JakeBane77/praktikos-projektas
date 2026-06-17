<?php

namespace App\Providers;

use App\Models\Alliance;
use App\Models\AllianceGoal;
use App\Models\Minigame;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserBuilding;
use App\Models\UserResource;
use App\Models\WeatherSnapshot;
use App\Policies\AllianceGoalPolicy;
use App\Policies\AlliancePolicy;
use App\Policies\MinigamePolicy;
use App\Policies\UserAchievementPolicy;
use App\Policies\UserBuildingPolicy;
use App\Policies\UserResourcePolicy;
use App\Policies\WeatherSnapshotPolicy;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthorization();
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure model ownership policies.
     */
    protected function configureAuthorization(): void
    {
        Gate::policy(UserBuilding::class, UserBuildingPolicy::class);
        Gate::policy(UserAchievement::class, UserAchievementPolicy::class);
        Gate::policy(UserResource::class, UserResourcePolicy::class);
        Gate::policy(Minigame::class, MinigamePolicy::class);
        Gate::policy(WeatherSnapshot::class, WeatherSnapshotPolicy::class);
        Gate::policy(Alliance::class, AlliancePolicy::class);
        Gate::policy(AllianceGoal::class, AllianceGoalPolicy::class);
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
     * Configure named rate limiters used by application routes.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('minigames', function (Request $request): array {
            $user = $request->user();

            $key = implode(':', [
                'minigame',
                $user instanceof User ? (string) $user->id : $request->ip(),
                $request->route('resource') ?? 'unknown',
            ]);

            $response = fn (Request $request, array $headers) => redirect()
                ->route('dashboard')
                ->withErrors([
                    'minigame' => 'Minigame completions are being submitted too quickly. Please wait a moment.',
                ])
                ->withHeaders($headers);

            return [
                Limit::perMinute(20)->by($key.':minute')->response($response),
                Limit::perHour(300)->by($key.':hour')->response($response),
            ];
        });

        RateLimiter::for('weather-location', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by($this->rateLimiterKey($request, 'weather-location'))
                ->response(
                    $this->throttledRedirectResponse(
                        'weather_location',
                        'Weather location updates are being submitted too quickly. Please wait a moment.',
                    ),
                );
        });

        RateLimiter::for('alliance-membership', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by($this->rateLimiterKey($request, 'alliance-membership'))
                ->response(
                    $this->throttledRedirectResponse(
                        'alliance',
                        'Alliance join and application requests are being submitted too quickly. Please wait a moment.',
                    ),
                );
        });

        RateLimiter::for('alliance-create', function (Request $request): Limit {
            return Limit::perMinutes(10, 3)
                ->by($this->rateLimiterKey($request, 'alliance-create'))
                ->response(
                    $this->throttledRedirectResponse(
                        'alliance',
                        'Alliance creation requests are being submitted too quickly. Please wait a moment.',
                    ),
                );
        });

        RateLimiter::for('alliance-admin', function (Request $request): Limit {
            return Limit::perMinute(10)
                ->by($this->rateLimiterKey($request, 'alliance-admin'))
                ->response(
                    $this->throttledRedirectResponse(
                        'alliance',
                        'Alliance management actions are being submitted too quickly. Please wait a moment.',
                    ),
                );
        });

        RateLimiter::for('alliance-chat', function (Request $request): Limit {
            return Limit::perMinute(30)
                ->by($this->rateLimiterKey($request, 'alliance-chat'))
                ->response(
                    $this->throttledRedirectResponse(
                        'alliance_chat',
                        'Alliance chat messages are being submitted too quickly. Please wait a moment.',
                    ),
                );
        });

        RateLimiter::for('alliance-contribute', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by($this->rateLimiterKey($request, 'alliance-contribute'))
                ->response(
                    $this->throttledRedirectResponse(
                        'alliance_goal',
                        'Alliance goal contributions are being submitted too quickly. Please wait a moment.',
                    ),
                );
        });
    }

    private function rateLimiterKey(Request $request, string $prefix): string
    {
        $user = $request->user();

        return implode(':', [
            $prefix,
            $user instanceof User ? (string) $user->id : $request->ip(),
            $request->route()?->getName() ?? 'unknown',
        ]);
    }

    private function throttledRedirectResponse(string $errorKey, string $message): \Closure
    {
        return fn (Request $request, array $headers) => redirect()
            ->to(url()->previous(route('dashboard')))
            ->withErrors([
                $errorKey => $message,
            ])
            ->withHeaders($headers);
    }
}
