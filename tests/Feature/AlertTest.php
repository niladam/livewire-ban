<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Niladam\LivewireBan\Mail\IpBanned;
use Niladam\LivewireBan\Models\Ban;

it('emails the configured recipient when an address is banned', function () {
    Mail::fake();
    Config::set('livewire-ban.alerts.to', 'security@example.test');

    strike(3);

    Mail::assertQueued(
        IpBanned::class,
        fn (IpBanned $mail): bool => $mail->hasTo('security@example.test') && $mail->ban->ip === SUSPECT_IP,
    );
});

it('falls back to the application from-address when no recipient is configured', function () {
    Mail::fake();
    Config::set('livewire-ban.alerts.to', null);

    strike(3);

    Mail::assertQueued(IpBanned::class, fn (IpBanned $mail): bool => $mail->hasTo('security@example.test'));
});

it('sends nothing while alerts are switched off', function () {
    Mail::fake();
    Config::set('livewire-ban.alerts.enabled', false);

    strike(3);

    expect(Ban::count())->toBe(1);

    Mail::assertNothingQueued();
});

it('stops alerting once the hourly ceiling is reached, but keeps banning', function () {
    Mail::fake();
    Config::set('livewire-ban.alerts.per_hour', 2);

    strike(3, '203.0.113.1');
    strike(3, '203.0.113.2');
    strike(3, '203.0.113.3');

    expect(Ban::count())->toBe(3);

    Mail::assertQueuedCount(2);
});

it('renders the alert with the offending details and a signed unban link', function () {
    $ban = Ban::factory()->create(['ip' => SUSPECT_IP, 'component' => 'checkout']);

    $rendered = (new IpBanned($ban))->render();

    expect($rendered)
        ->toContain(SUSPECT_IP)
        ->toContain('checkout')
        ->toContain('CorruptComponentPayloadException')
        ->toContain('Lift this ban')
        ->toContain(route('livewire-ban.unban', ['ban' => $ban->getKey()], absolute: false));
});

it('warns when the cloudflare header disagrees with the connecting address', function () {
    $ban = Ban::factory()->offOrigin()->create(['ip' => SUSPECT_IP]);

    expect($ban->mismatchesCloudflareIp())->toBeTrue()
        ->and((new IpBanned($ban))->render())->toContain('without passing through Cloudflare');
});

it('does not warn when the request came through cloudflare', function () {
    $ban = Ban::factory()->create(['ip' => SUSPECT_IP]);

    expect($ban->mismatchesCloudflareIp())->toBeFalse()
        ->and((new IpBanned($ban))->render())->not->toContain('without passing through Cloudflare');
});
