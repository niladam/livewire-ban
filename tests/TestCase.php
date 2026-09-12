<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests;

use Livewire\LivewireServiceProvider;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\LivewireBanServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            LivewireBanServiceProvider::class,
        ];
    }

    /** @return array<string, class-string> */
    protected function getPackageAliases($app): array
    {
        return [
            'LivewireBan' => LivewireBan::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // The install command publishes into the shared skeleton application.
        foreach (glob(database_path('migrations/*_create_livewire_bans_table.php')) ?: [] as $stray) {
            @unlink($stray);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.from.address', 'security@example.test');
    }
}
