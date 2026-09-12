<?php

declare(strict_types=1);

namespace Niladam\LivewireBan;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Niladam\LivewireBan\Commands\InstallCommand;
use Niladam\LivewireBan\Http\Middleware\BanLivewireBots;
use Niladam\LivewireBan\Models\Ban;

class LivewireBanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/livewire-ban.php', 'livewire-ban');

        $this->app->singleton(Warden::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'livewire-ban');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'livewire-ban');

        $this->registerPolicy();
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerPublishing();
    }

    /**
     * Policy discovery cannot find anything for a model inside a package, so
     * the binding is explicit. Gate falls back to is_subclass_of, so the base
     * model covers a swapped-in one.
     */
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
     * Prepended so a banned address is turned away before any work is done on
     * its behalf. Assumes the standard HTTP kernel; anything else should set
     * register_middleware to false and place the middleware itself.
     */
    private function registerMiddleware(): void
    {
        if (! Config::boolean('livewire-ban.register_middleware', true)) {
            return;
        }

        // Resolved through the contract so this is the bound singleton, not a
        // fresh kernel whose middleware stack nothing would ever read.
        $this->app->make(Kernel::class)->prependMiddleware(BanLivewireBots::class);
    }

    /**
     * Stamped with the current time so it lands after whatever the application
     * already has, rather than claiming a fixed slot in their history.
     */
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
