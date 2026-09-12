<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Niladam\LivewireBan\Enums\BlockScope;
use Niladam\LivewireBan\LivewireBanServiceProvider;
use Niladam\LivewireBan\Tests\TestCase;

/**
 * Written as a class rather than a Pest closure because the override has to be
 * in place before the service provider registers.
 */
class ConfigMergeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('livewire-ban', ['strikes' => 1]);

        // Testbench applies this after the provider has already registered. A
        // real application loads config/livewire-ban.php first, so re-register
        // to reproduce that order.
        $app->register(LivewireBanServiceProvider::class, force: true);
    }

    public function test_an_application_override_wins(): void
    {
        $this->assertSame(1, Config::integer('livewire-ban.strikes'));
    }

    /**
     * mergeConfigFrom is a top-level array_merge, so a key the application does
     * not name keeps the packaged value. A key it does name is replaced whole,
     * which is why a published config has to be the complete file.
     */
    public function test_keys_the_application_left_alone_keep_their_defaults(): void
    {
        $this->assertSame(10.0, Config::get('livewire-ban.window')->totalMinutes);
        $this->assertSame(BlockScope::Site, Config::get('livewire-ban.block'));
        $this->assertCount(2, Config::array('livewire-ban.triggers'));
        $this->assertSame(10, Config::integer('livewire-ban.alerts.per_hour'));
    }
}
