<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Models\Ban;
use Niladam\LivewireBan\Tests\Fixtures\RolelessUser;
use Niladam\LivewireBan\Tests\Fixtures\StaffUser;

it('checks nothing while no roles are configured', function () {
    $this->actingAs(new StaffUser(['roles' => ['admin']]));

    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('never strikes a request from a user holding an exempt role', function () {
    Config::set('livewire-ban.exempt_roles', ['admin']);

    $this->actingAs(new StaffUser(['roles' => ['admin']]));

    strike(5);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and(Ban::count())->toBe(0);
});

it('still bans a signed-in user who holds none of them', function () {
    Config::set('livewire-ban.exempt_roles', ['admin']);

    $this->actingAs(new StaffUser(['roles' => ['customer']]));

    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('still bans a guest', function () {
    Config::set('livewire-ban.exempt_roles', ['admin']);

    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('asks whichever method the application names', function () {
    Config::set('livewire-ban.exempt_roles', ['admin']);
    Config::set('livewire-ban.exempt_roles_via', 'isOneOf');

    $this->actingAs(new StaffUser(['roles' => ['admin']]));

    strike(5);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('bans normally when the user model has no such method', function () {
    Config::set('livewire-ban.exempt_roles', ['admin']);

    $this->actingAs(new RolelessUser(['roles' => ['admin']]));

    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});
