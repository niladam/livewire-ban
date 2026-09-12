<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Livewire\Exceptions\ComponentNotFoundException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Enums\BlockScope;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Http\Middleware\BanLivewireBots;
use Symfony\Component\HttpKernel\Exception\HttpException;

function middleware(): BanLivewireBots
{
    return app(BanLivewireBots::class);
}

it('lets an unbanned address through untouched', function () {
    $response = middleware()->handle(livewireRequest(), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('rethrows a trigger exception instead of swallowing it', function () {
    middleware()->handle(livewireRequest(), fn () => throw new CorruptComponentPayloadException);
})->throws(CorruptComponentPayloadException::class);

it('rethrows an exception it does not recognise', function () {
    middleware()->handle(livewireRequest(), fn () => throw new RuntimeException('a real bug'));
})->throws(RuntimeException::class);

it('bans an address after three trigger exceptions escape the pipeline', function () {
    throughMiddleware(new CorruptComponentPayloadException, 3);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});

it('ignores exceptions that are not configured triggers', function () {
    throughMiddleware(new RuntimeException('a real bug'), 5);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('ignores a missing component, which a broken blade include would throw', function () {
    throughMiddleware(new ComponentNotFoundException('nope'), 5);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('forbids every request from a banned address by default', function () {
    throughMiddleware(new CorruptComponentPayloadException, 3);

    expect(fn () => middleware()->handle(
        Request::create('/', 'GET', server: ['REMOTE_ADDR' => SUSPECT_IP]),
        fn () => response('ok'),
    ))->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403));
});

it('forbids only livewire requests when the scope is narrowed', function () {
    Config::set('livewire-ban.block', BlockScope::Livewire);

    throughMiddleware(new CorruptComponentPayloadException, 3);

    $browsing = middleware()->handle(
        Request::create('/', 'GET', server: ['REMOTE_ADDR' => SUSPECT_IP]),
        fn () => response('ok'),
    );

    expect($browsing->getContent())->toBe('ok');

    $livewire = Request::create('/livewire/update', 'POST', server: ['REMOTE_ADDR' => SUSPECT_IP]);
    $livewire->headers->set('X-Livewire', '1');

    expect(fn () => middleware()->handle($livewire, fn () => response('ok')))
        ->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403));
});

it('leaves other addresses alone when one is banned', function () {
    throughMiddleware(new CorruptComponentPayloadException, 3);

    $response = middleware()->handle(
        Request::create('/', 'GET', server: ['REMOTE_ADDR' => '198.51.100.7']),
        fn () => response('ok'),
    );

    expect($response->getContent())->toBe('ok');
});
