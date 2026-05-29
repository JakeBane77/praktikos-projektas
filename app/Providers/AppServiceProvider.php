<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        $this->configureDefaults();
        $this->configureRateLimiting();
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
            $key = implode(':', [
                'minigame',
                $request->user()?->id ?? $request->ip(),
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
    }
}
