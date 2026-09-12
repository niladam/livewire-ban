<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Niladam\LivewireBan\Enums\BlockScope;
use Niladam\LivewireBan\LivewireBanServiceProvider;

use function Orchestra\Testbench\Pest\defineEnvironment;

defineEnvironment(function ($app) {
    $app['config']->set('livewire-ban', ['strikes' => 1]);

    // Testbench sets config after the provider registered; a real application
    // loads it first, so re-register to reproduce that order.
    $app->register(LivewireBanServiceProvider::class, force: true);
});

it('lets an application override win', function () {
    expect(Config::integer('livewire-ban.strikes'))->toBe(1);
});

// The merge is a top-level array_merge, which is why a published config has to
// be the complete file.
it('keeps the defaults for keys the application left alone', function () {
    expect(Config::get('livewire-ban.window')->totalMinutes)->toBe(10.0)
        ->and(Config::get('livewire-ban.block'))->toBe(BlockScope::Site)
        ->and(Config::array('livewire-ban.triggers'))->toHaveCount(2)
        ->and(Config::integer('livewire-ban.alerts.per_hour'))->toBe(10);
});
