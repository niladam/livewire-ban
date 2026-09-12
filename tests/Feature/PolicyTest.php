<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Models\Ban;
use Niladam\LivewireBan\Tests\Fixtures\SubclassedCorruption;

use function Illuminate\Support\hours;

it('bans on the very first strike when the threshold is one', function () {
    Config::set('livewire-ban.strikes', 1);

    strike(1);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue()
        ->and(Ban::sole()->strikes)->toBe(1);
});

it('bans permanently when the escalation rung is null', function () {
    Config::set('livewire-ban.escalation', [null]);

    strike(3);

    expect(Ban::sole()->expires_at)->toBeNull()
        ->and(Ban::sole()->isPermanent())->toBeTrue()
        ->and(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('keeps a permanent ban in force however much time passes', function () {
    Config::set('livewire-ban.escalation', [null]);

    strike(3);

    $this->travel(5)->years();

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('escalates to a permanent ban on the final rung', function () {
    Config::set('livewire-ban.escalation', [hours(1), null]);

    strike(3);
    strike(3);

    $bans = Ban::orderBy('id')->get();

    expect($bans[0]->expires_at)->not->toBeNull()
        ->and($bans[1]->expires_at)->toBeNull();
});

it('still lists a permanent ban as active', function () {
    Config::set('livewire-ban.escalation', [null]);

    strike(3);

    expect(LivewireBan::query()->active()->count())->toBe(1);
});

it('lifts a permanent ban like any other', function () {
    Config::set('livewire-ban.escalation', [null]);

    strike(3);

    LivewireBan::unban(Ban::sole());

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and(LivewireBan::query()->active()->count())->toBe(0);
});

it('counts any exception class the application adds to the trigger list', function () {
    Config::set('livewire-ban.triggers', [RuntimeException::class]);

    requestThatThrows(new RuntimeException('something the app considers hostile'), 3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue()
        ->and(Ban::sole()->exception_class)->toBe(RuntimeException::class);
});

it('counts a subclass of a configured trigger', function () {
    requestThatThrows(new SubclassedCorruption, 3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue()
        ->and(Ban::sole()->exception_class)->toBe(SubclassedCorruption::class);
});

it('leaves unrelated exceptions alone even when a trigger list is set', function () {
    Config::set('livewire-ban.triggers', [RuntimeException::class]);

    requestThatThrows(new LogicException('unrelated'), 5);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});
