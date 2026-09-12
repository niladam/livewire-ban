<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\LivewireBanServiceProvider;
use Niladam\LivewireBan\Tests\TestCase;

/**
 * Detection replaces the handler binding with one wrapped around whatever it
 * found, so a provider that boots a second time would wrap its own wrapper and
 * charge two strikes for one exception. Forcing a provider to register again is
 * how an application re-registers to win a config merge, and Laravel boots it
 * on the spot once the application is already booted.
 */
class DoubleRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const string SUSPECT = '203.0.113.43';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('livewire-ban.strikes', 2);
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/boom', fn () => throw new CorruptComponentPayloadException);
    }

    public function test_one_exception_costs_one_strike(): void
    {
        $this->app->register(LivewireBanServiceProvider::class, force: true);

        $this->withServerVariables(['REMOTE_ADDR' => self::SUSPECT])->get('/boom');

        $this->assertFalse(LivewireBan::banned(self::SUSPECT));

        $this->withServerVariables(['REMOTE_ADDR' => self::SUSPECT])->get('/boom');

        $this->assertTrue(LivewireBan::banned(self::SUSPECT));
    }
}
