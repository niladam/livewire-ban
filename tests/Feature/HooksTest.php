<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Events\Banned;
use Niladam\LivewireBan\Events\Struck;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Http\Middleware\BanLivewireBots;
use Niladam\LivewireBan\Models\Ban;
use Niladam\LivewireBan\Tests\Fixtures\CustomBan;

it('reads the client address through a resolveIpUsing hook', function () {
    LivewireBan::resolveIpUsing(fn (Request $request): string => (string) $request->header('CF-Connecting-IP'));

    $request = tamperedRequest();
    $request->headers->set('CF-Connecting-IP', '198.51.100.4');

    foreach (range(1, 3) as $ignored) {
        LivewireBan::strike($request, new CorruptComponentPayloadException);
    }

    expect(LivewireBan::banned('198.51.100.4'))->toBeTrue()
        ->and(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and(Ban::sole()->ip)->toBe('198.51.100.4');
});

it('skips a request an exemptUsing hook claims', function () {
    LivewireBan::exemptUsing(fn (Request $request): bool => $request->ip() === SUSPECT_IP);

    strike(5);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and(Ban::count())->toBe(0);
});

it('lets a strikeUsing hook count an exception the trigger list ignores', function () {
    LivewireBan::strikeUsing(fn (Throwable $e): ?bool => $e instanceof RuntimeException ? true : null);

    strike(3, e: new RuntimeException('handcrafted probe'));

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue()
        ->and(Ban::sole()->exception_class)->toBe(RuntimeException::class);
});

it('lets a strikeUsing hook veto an exception the trigger list would count', function () {
    LivewireBan::strikeUsing(fn (): ?bool => false);

    $middleware = app(BanLivewireBots::class);

    foreach (range(1, 5) as $ignored) {
        try {
            $middleware->handle(tamperedRequest(), fn () => throw new CorruptComponentPayloadException);
        } catch (Throwable) {
            // rethrown by design
        }
    }

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('defers to the trigger list when strikeUsing returns null', function () {
    LivewireBan::strikeUsing(fn (): ?bool => null);

    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('replaces the ban routine with a banUsing hook', function () {
    $seen = [];

    LivewireBan::banUsing(function (Request $request, Throwable $e, int $strikes) use (&$seen): ?Ban {
        $seen = ['ip' => $request->ip(), 'strikes' => $strikes, 'exception' => $e::class];

        return null;
    });

    strike(3);

    expect($seen)->toBe([
        'ip' => SUSPECT_IP,
        'strikes' => 3,
        'exception' => CorruptComponentPayloadException::class,
    ])
        ->and(Ban::count())->toBe(0)
        ->and(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('lets a banUsing hook delegate back to the default routine', function () {
    LivewireBan::banUsing(fn (Request $request, Throwable $e, int $strikes, $warden): Ban => $warden->ban($request, $e, $strikes));

    strike(3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue()
        ->and(Ban::count())->toBe(1);
});

it('writes through a swapped-in model', function () {
    LivewireBan::useModel(CustomBan::class);

    strike(3);

    expect(LivewireBan::model())->toBe(CustomBan::class)
        ->and(LivewireBan::query()->sole())->toBeInstanceOf(CustomBan::class)
        ->and(CustomBan::sole()->shout())->toBe(SUSPECT_IP);
});

it('reads the model from config when no override is registered', function () {
    Config::set('livewire-ban.model', CustomBan::class);

    expect(LivewireBan::model())->toBe(CustomBan::class);
});

it('refuses a model that does not extend the packaged one', function () {
    LivewireBan::useModel(stdClass::class);

    LivewireBan::model();
})->throws(InvalidArgumentException::class);

it('announces every strike so an application can watch the build-up', function () {
    Event::fake([Struck::class, Banned::class]);

    strike(2);

    Event::assertDispatchedTimes(Struck::class, 2);
    Event::assertNotDispatched(Banned::class);
});

it('announces the ban itself', function () {
    Event::fake([Banned::class]);

    strike(3);

    Event::assertDispatched(Banned::class, fn (Banned $event): bool => $event->ban->ip === SUSPECT_IP);
});
