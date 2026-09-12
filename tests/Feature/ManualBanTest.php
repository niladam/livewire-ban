<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Niladam\LivewireBan\Events\Banned;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Mail\IpBanned;
use Niladam\LivewireBan\Models\Ban;

use function Illuminate\Support\days;
use function Illuminate\Support\hours;

it('bans an address outright, with no request and no exception', function () {
    $ban = LivewireBan::banIp('198.51.100.7', 'Scraping product pages');

    expect(LivewireBan::banned('198.51.100.7'))->toBeTrue()
        ->and($ban->ip)->toBe('198.51.100.7')
        ->and($ban->exception_message)->toBe('Scraping product pages')
        ->and($ban->exception_class)->toBeNull()
        ->and($ban->isManual())->toBeTrue();
});

it('follows the escalation ladder when no duration is given', function () {
    Config::set('livewire-ban.escalation', [hours(1), hours(6)]);

    $first = LivewireBan::banIp('198.51.100.7');
    $second = LivewireBan::banIp('198.51.100.7');

    expect($first->banned_at->diffInHours($first->expires_at, absolute: true))->toBe(1.0)
        ->and($second->banned_at->diffInHours($second->expires_at, absolute: true))->toBe(6.0)
        ->and($second->offence)->toBe(2);
});

it('honours an explicit duration', function () {
    $ban = LivewireBan::banIp('198.51.100.7', for: days(30));

    expect($ban->banned_at->diffInDays($ban->expires_at, absolute: true))->toBe(30.0);
});

it('bans an address forever', function () {
    $ban = LivewireBan::banIpForever('198.51.100.7', 'Known bad actor');

    expect($ban->isPermanent())->toBeTrue()
        ->and($ban->expires_at)->toBeNull()
        ->and(LivewireBan::banned('198.51.100.7'))->toBeTrue();
});

it('keeps a permanent manual ban in force however much time passes', function () {
    LivewireBan::banIpForever('198.51.100.7');

    $this->travel(3)->years();

    expect(LivewireBan::banned('198.51.100.7'))->toBeTrue();
});

it('alerts and announces a manual ban like any other', function () {
    Mail::fake();
    Event::fake([Banned::class]);
    Config::set('livewire-ban.alerts.to', 'security@example.test');

    LivewireBan::banIp('198.51.100.7', 'Scraping');

    Mail::assertQueued(IpBanned::class);
    Event::assertDispatched(Banned::class);
});

it('lifts a manual ban by address', function () {
    LivewireBan::banIp('198.51.100.7');

    LivewireBan::unbanIp('198.51.100.7');

    expect(LivewireBan::banned('198.51.100.7'))->toBeFalse()
        ->and(Ban::sole()->unbanned_at)->not->toBeNull();
});

it('shrugs off lifting an address that was never banned', function () {
    LivewireBan::unbanIp('203.0.113.200');

    expect(Ban::count())->toBe(0);
});

it('renders the alert for a ban that has no exception behind it', function () {
    $ban = LivewireBan::banIp('198.51.100.7', 'Scraping product pages');

    expect((new IpBanned($ban))->render())
        ->toContain('198.51.100.7')
        ->toContain('Scraping product pages');
});
