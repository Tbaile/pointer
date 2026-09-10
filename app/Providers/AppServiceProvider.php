<?php

namespace App\Providers;

use App\Contracts\KnowledgeRetriever;
use App\Contracts\RemoteExecutor;
use App\Services\Kapa\KapaNsecRetriever;
use App\Services\Remote\SanchoExecutor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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

        $this->app->bind(
            KnowledgeRetriever::class,
            fn (): KapaNsecRetriever => new KapaNsecRetriever(
                apiKey: config('services.kapa.api_key'),
                projectId: config('services.kapa.project_id'),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
