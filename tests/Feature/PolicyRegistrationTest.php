<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Niladam\LivewireBan\LivewireBanServiceProvider;
use Niladam\LivewireBan\Models\Ban;
use Niladam\LivewireBan\Tests\Fixtures\AllowNothingPolicy;
use Niladam\LivewireBan\Tests\Fixtures\CustomBan;
use Niladam\LivewireBan\Tests\TestCase;

/**
 * A class rather than a Pest closure because the config has to be in place
 * before the service provider boots.
 */
class PolicyRegistrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('livewire-ban.policy', AllowNothingPolicy::class);

        $app->register(LivewireBanServiceProvider::class, force: true);
    }

    public function test_the_package_registers_the_configured_policy(): void
    {
        $this->assertInstanceOf(AllowNothingPolicy::class, Gate::getPolicyFor(Ban::class));
    }

    public function test_a_swapped_in_model_inherits_the_same_policy(): void
    {
        $this->assertInstanceOf(AllowNothingPolicy::class, Gate::getPolicyFor(CustomBan::class));
    }
}
