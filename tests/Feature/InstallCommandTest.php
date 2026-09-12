<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Niladam\LivewireBan\LivewireBanServiceProvider;

use function Illuminate\Support\hours;
use function Illuminate\Support\minutes;

it('runs and reports what is already in force', function () {
    Config::set('livewire-ban.strikes', 3);
    Config::set('livewire-ban.window', minutes(10));
    Config::set('livewire-ban.escalation', [hours(1), hours(6)]);
    Config::set('livewire-ban.alerts.to', 'security@example.test');

    // Every value the summary renders is read live, so a rename or a moved
    // method fails the command outright rather than printing something stale.
    $this->artisan('livewire-ban:install')->assertSuccessful();
});

it('reports a permanent first ban without blowing up', function () {
    Config::set('livewire-ban.escalation', [null]);

    $this->artisan('livewire-ban:install')
        ->assertSuccessful()
        ->expectsOutputToContain('permanent');
});

it('survives an empty escalation ladder', function () {
    Config::set('livewire-ban.escalation', []);

    $this->artisan('livewire-ban:install')->assertSuccessful();
});

it('offers the migration for publishing, because the application owns its own schema', function () {
    $published = ServiceProvider::pathsToPublish(LivewireBanServiceProvider::class, 'livewire-ban-migrations');

    expect($published)->toHaveCount(1)
        ->and(array_key_first($published))->toEndWith('create_livewire_bans_table.php')
        ->and(reset($published))->toContain('database/migrations')
        ->and(reset($published))->toMatch('/\\d{4}_\\d{2}_\\d{2}_\\d{6}_create_livewire_bans_table\\.php$/');
});

it('keeps the config out of the way until it is asked for', function () {
    $published = ServiceProvider::pathsToPublish(LivewireBanServiceProvider::class, 'livewire-ban-config');

    expect($published)->toHaveCount(1);
});
