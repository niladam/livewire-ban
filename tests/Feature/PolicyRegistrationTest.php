<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Niladam\LivewireBan\LivewireBanServiceProvider;
use Niladam\LivewireBan\Models\Ban;
use Niladam\LivewireBan\Tests\Fixtures\AllowNothingPolicy;
use Niladam\LivewireBan\Tests\Fixtures\CustomBan;

use function Orchestra\Testbench\Pest\defineEnvironment;

defineEnvironment(function ($app) {
    $app['config']->set('livewire-ban.policy', AllowNothingPolicy::class);

    $app->register(LivewireBanServiceProvider::class, force: true);
});

it('registers the configured policy', function () {
    expect(Gate::getPolicyFor(Ban::class))->toBeInstanceOf(AllowNothingPolicy::class);
});

it('gives a swapped-in model the same policy', function () {
    expect(Gate::getPolicyFor(CustomBan::class))->toBeInstanceOf(AllowNothingPolicy::class);
});
