<?php

namespace App\Providers;

use App\Contracts\RemoteExecutor;
use App\Enums\Role;
use App\Models\User;
use App\Services\Remote\SanchoExecutor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            RemoteExecutor::class,
            function (): SanchoExecutor {
                $host = config('pointer.support.host');

                if (! is_string($host) || $host === '') {
                    throw new RuntimeException(
                        'No support server configured. Set POINTER_SUPPORT_HOST in your environment.',
                    );
                }

                $identityFile = config('pointer.support.identity_file');
                $identityFile = is_string($identityFile) && $identityFile !== ''
                    ? (str_starts_with($identityFile, '/') ? $identityFile : storage_path($identityFile))
                    : null;

                return new SanchoExecutor(
                    host: $host,
                    user: config('pointer.support.user'),
                    identityFile: $identityFile,
                    timeout: config('pointer.execution.timeout'),
                    maxOutputBytes: config('pointer.execution.max_output_bytes'),
                );
            },
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // PHP_CLI_SERVER_WORKERS is ignored without --no-reload
        DevCommands::artisan('serve --no-reload', 'server');

        Passport::authorizationView(fn (array $parameters): Response => response()->view('pages.authorize', $parameters));

        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::SuperAdmin) ? true : null);
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
