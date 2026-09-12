<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Models\Ban;

use function Illuminate\Support\days;
use function Illuminate\Support\hours;
use function Illuminate\Support\minutes;

it('does not ban an address that stays under the strike threshold', function () {
    strike(2);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and(Ban::count())->toBe(0);
});

it('bans an address once it reaches the strike threshold', function () {
    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();

    $ban = Ban::sole();

    expect($ban->ip)->toBe(SUSPECT_IP)
        ->and($ban->exception_class)->toBe(CorruptComponentPayloadException::class)
        ->and($ban->strikes)->toBe(3)
        ->and($ban->offence)->toBe(1)
        ->and($ban->expires_at)->toBeGreaterThan(now());
});

it('records what the caller tried to change', function () {
    strike(3);

    $ban = Ban::sole();

    expect($ban->component)->toBe('checkout')
        ->and($ban->components()[0]['name'])->toBe('checkout')
        ->and($ban->components()[0]['updates'])->toBe(['isAdmin' => true]);
});

it('counts strikes per address rather than globally', function () {
    strike(2, '203.0.113.1');
    strike(2, '203.0.113.2');

    expect(LivewireBan::banned('203.0.113.1'))->toBeFalse()
        ->and(LivewireBan::banned('203.0.113.2'))->toBeFalse()
        ->and(Ban::count())->toBe(0);
});

it('forgets strikes once the window has passed', function () {
    Config::set('livewire-ban.window', minutes(10));

    strike(2);

    $this->travel(11)->minutes();

    strike(2);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('treats a locked-property update as a strike too', function () {
    strike(3, e: new CannotUpdateLockedPropertyException('isAdmin'));

    expect(Ban::sole()->exception_class)->toBe(CannotUpdateLockedPropertyException::class);
});

it('lengthens the ban each time the same address re-offends within a day', function () {
    Config::set('livewire-ban.escalation', [hours(1), hours(6), days(1)]);

    strike(3);
    strike(3);
    strike(3);

    $bans = Ban::orderBy('id')->get();

    expect($bans->pluck('offence')->all())->toBe([1, 2, 3])
        ->and($bans[0]->banned_at->diffInHours($bans[0]->expires_at, absolute: true))->toBe(1.0)
        ->and($bans[1]->banned_at->diffInHours($bans[1]->expires_at, absolute: true))->toBe(6.0)
        ->and($bans[2]->banned_at->diffInHours($bans[2]->expires_at, absolute: true))->toBe(24.0);
});

it('repeats the last rung once the escalation ladder is exhausted', function () {
    Config::set('livewire-ban.escalation', [hours(1), hours(6)]);

    strike(3);
    strike(3);
    strike(3);

    $last = Ban::orderByDesc('id')->first();

    expect($last->offence)->toBe(3)
        ->and($last->banned_at->diffInHours($last->expires_at, absolute: true))->toBe(6.0);
});

it('stops blocking once the ban expires', function () {
    Config::set('livewire-ban.escalation', [hours(1)]);

    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();

    $this->travel(2)->hours();

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('lifts a ban on demand and clears the block immediately', function () {
    strike(3);

    LivewireBan::unban($ban = Ban::sole());

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and($ban->refresh()->unbanned_at)->not->toBeNull();
});

it('records nothing while the kill switch is off', function () {
    Config::set('livewire-ban.enabled', false);

    strike(5);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and(Ban::count())->toBe(0);
});

it('reports an existing ban as lifted while the kill switch is off', function () {
    Config::set('livewire-ban.enabled', false);

    Ban::factory()->create(['ip' => SUSPECT_IP]);
    Cache::put('livewire-ban:'.SUSPECT_IP, true, now()->addHour());

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('reads its settings once, when the container builds it', function () {
    // Resolves the Warden, freezing the three-strike default.
    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();

    Config::set('livewire-ban.strikes', 1);

    strike(1);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('never bans an explicitly allowlisted address', function () {
    Config::set('livewire-ban.allowlist', [SUSPECT_IP]);

    strike(5);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and(Ban::count())->toBe(0);
});

it('never bans an address inside an allowlisted cidr range', function () {
    Config::set('livewire-ban.allowlist', ['203.0.113.0/24']);

    strike(5);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('still bans an address outside the allowlisted range', function () {
    Config::set('livewire-ban.allowlist', ['198.51.100.0/24']);

    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('honours a custom exemption callback', function () {
    Config::set('livewire-ban.exempt_using', fn (): bool => true);

    strike(5);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and(Ban::count())->toBe(0);
});

it('bans normally when the custom exemption declines', function () {
    Config::set('livewire-ban.exempt_using', fn (): bool => false);

    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('does not let a throwing exemption callback mask the strike', function () {
    Config::set('livewire-ban.exempt_using', function (): bool {
        throw new RuntimeException('the host application is broken');
    });

    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});
