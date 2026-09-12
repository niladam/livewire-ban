<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Models\Ban;

function unbanUrl(Ban $ban): string
{
    return URL::temporarySignedRoute('livewire-ban.unban', now()->addDay(), ['ban' => $ban->getKey()]);
}

it('lifts the ban when the signed link is opened', function () {
    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();

    $this->get(unbanUrl($ban = Ban::sole()))->assertOk();

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and($ban->refresh()->unbanned_at)->not->toBeNull();
});

it('rejects a link whose signature was tampered with', function () {
    strike(3);

    $this->get(unbanUrl(Ban::sole()).'&tampered=1')->assertForbidden();

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('rejects an unsigned link', function () {
    strike(3);

    $this->get('/livewire-ban/'.Ban::sole()->getKey().'/unban')->assertForbidden();

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('rejects an expired link', function () {
    strike(3);

    $url = URL::temporarySignedRoute('livewire-ban.unban', now()->addDay(), ['ban' => Ban::sole()->getKey()]);

    $this->travel(2)->days();

    $this->get($url)->assertForbidden();

    // The ban itself has long since lapsed by now; what matters is that the
    // stale link did not record an unban.
    expect(Ban::sole()->unbanned_at)->toBeNull();
});

it('is harmless when the same link is opened twice', function () {
    strike(3);

    $url = unbanUrl($ban = Ban::sole());

    $this->get($url)->assertOk();
    $liftedAt = $ban->refresh()->unbanned_at;

    $this->travel(5)->minutes();
    $this->get($url)->assertOk();

    expect($ban->refresh()->unbanned_at->equalTo($liftedAt))->toBeTrue();
});
