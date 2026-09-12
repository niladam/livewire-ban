<?php

declare(strict_types=1);

namespace Niladam\LivewireBan;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Niladam\LivewireBan\Commands\InstallCommand;
use Niladam\LivewireBan\Exceptions\DetectingExceptionHandler;
use Niladam\LivewireBan\Http\Middleware\BanLivewireBots;
use Niladam\LivewireBan\Models\Ban;

class LivewireBanServiceProvider extends ServiceProvider
{
    private const string DETECTING = 'livewire-ban.detecting';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/livewire-ban.php', 'livewire-ban');

        $this->app->singleton(Warden::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'livewire-ban');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'livewire-ban');

        $this->registerDetection();
        $this->registerPolicy();
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerPublishing();
    }

    /**
     * See DetectingExceptionHandler for why nothing lighter works. Resolved
     * before it is wrapped, and at booted(): withExceptions() lands on an
     * after-resolving callback that matches on type, so wrapping any earlier
     * costs the application its entire exception configuration.
     */
    private function registerDetection(): void
    {
        if (! Config::boolean('livewire-ban.enabled', true)) {
            return;
        }

        $this->app->booted(function (Application $app): void {
            // Registering twice would wrap the wrapper and double every strike.
            if ($app->bound(self::DETECTING)) {
                return;
            }

            $app->instance(self::DETECTING, true);

            $handler = $app->make(ExceptionHandler::class);

            $app->singleton(
                ExceptionHandler::class,
                fn (Application $container): ExceptionHandler => new DetectingExceptionHandler(
                    $handler,
                    $container->make(Warden::class),
                ),
            );
        });
    }

    /** Laravel discovers no policy for a model living inside a package. */
    private function registerPolicy(): void
    {
        $policy = Config::get('livewire-ban.policy');

        if (filled($policy)) {
            Gate::policy(Ban::class, $policy);
        }
    }

    private function registerRoutes(): void
    {
        Route::prefix(Config::string('livewire-ban.route.prefix', 'livewire-ban'))
            ->middleware(Config::array('livewire-ban.route.middleware', ['web', 'signed']))
            ->group(__DIR__.'/../routes/web.php');
    }

    /**
     * Prepended so a banned address is turned away before any work is done for
     * it. Assumes the standard HTTP kernel; register_middleware => false for
     * anything else, and place the middleware yourself.
     */
    private function registerMiddleware(): void
    {
        if (! Config::boolean('livewire-ban.register_middleware', true)) {
            return;
        }

        $this->app->make(Kernel::class)->prependMiddleware(BanLivewireBots::class);
    }

    /** Timestamped now so it lands after the migrations they already have. */
    private function migrationPath(): string
    {
        $existing = glob(database_path('migrations/*_create_livewire_bans_table.php'));

        return $existing === false || $existing === []
            ? database_path('migrations/'.date('Y_m_d_His').'_create_livewire_bans_table.php')
            : $existing[0];
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([InstallCommand::class]);

        $this->publishes([
            __DIR__.'/../config/livewire-ban.php' => config_path('livewire-ban.php'),
        ], 'livewire-ban-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/livewire-ban'),
        ], 'livewire-ban-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/livewire-ban'),
        ], 'livewire-ban-translations');

        $this->publishes([
            __DIR__.'/../database/migrations/create_livewire_bans_table.php' => $this->migrationPath(),
        ], 'livewire-ban-migrations');
    }
}
