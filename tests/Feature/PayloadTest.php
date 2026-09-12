<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Mail\IpBanned;
use Niladam\LivewireBan\Models\Ban;

/**
 * @param  list<array<string, mixed>>  $components
 */
function requestWith(array $components, string $ip = SUSPECT_IP): Request
{
    return Request::create('/livewire/update', 'POST', ['components' => $components], server: ['REMOTE_ADDR' => $ip]);
}

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, mixed>  $updates
 * @param  list<mixed>  $calls
 * @return array<string, mixed>
 */
function component(
    string $name,
    array $data = [],
    array $updates = [],
    array $calls = [],
    string $id = 'aBc12345',
    string $path = '/'
): array {
    return [
        'snapshot' => json_encode([
            'data' => $data,
            'memo' => ['id' => $id, 'name' => $name, 'path' => $path],
            'checksum' => 'whatever',
        ]),
        'updates' => $updates,
        'calls' => $calls,
    ];
}

function strikeRequest(Request $request, Throwable $e, int $times = 3): void
{
    foreach (range(1, $times) as $ignored) {
        LivewireBan::strike($request, $e);
    }
}

it('names the locked property the caller went after', function () {
    strikeRequest(
        requestWith([component('support-chat', updates: ['error' => []])]),
        new CannotUpdateLockedPropertyException('error'),
    );

    expect(Ban::sole()->targetedProperty())->toBe('error');
});

it('leaves the target empty for an exception that names no property', function () {
    strikeRequest(
        requestWith([component('support-chat')]),
        new CorruptComponentPayloadException,
    );

    expect(Ban::sole()->targetedProperty())->toBeNull();
});

it('blames the component that carried the locked update, not merely the first', function () {
    strikeRequest(
        requestWith([
            component('cart-counter', updates: ['quantity' => 2]),
            component('support-chat', updates: ['error' => []]),
        ]),
        new CannotUpdateLockedPropertyException('error'),
    );

    expect(Ban::sole()->component)->toBe('support-chat');
});

it('falls back to the first component when nothing points at one', function () {
    strikeRequest(
        requestWith([
            component('cart-counter'),
            component('support-chat'),
        ]),
        new CorruptComponentPayloadException,
    );

    expect(Ban::sole()->component)->toBe('cart-counter');
});

it('records the snapshot id and the page the snapshot was lifted from', function () {
    strikeRequest(
        requestWith([component('support-chat', id: 'Xy9', path: 'contact')]),
        new CorruptComponentPayloadException,
    );

    $component = Ban::sole()->components()[0];

    expect($component['id'])->toBe('Xy9')
        ->and($component['path'])->toBe('contact')
        ->and($component['name'])->toBe('support-chat');
});

it('records which properties the snapshot held, never their values', function () {
    strikeRequest(
        requestWith([component('checkout', data: [
            'email' => 'victim@example.test',
            'cardToken' => 'tok_secret',
        ])]),
        new CorruptComponentPayloadException,
    );

    $ban = Ban::sole();

    expect($ban->components()[0]['properties'])->toBe(['email', 'cardToken'])
        ->and(json_encode($ban->context))->not->toContain('victim@example.test')
        ->and(json_encode($ban->context))->not->toContain('tok_secret');
});

it('keeps the updates and calls, which are the evidence', function () {
    strikeRequest(
        requestWith([component('admin-panel', updates: ['isAdmin' => true], calls: [['method' => 'destroy', 'params' => [1]]])]),
        new CorruptComponentPayloadException,
    );

    $component = Ban::sole()->components()[0];

    expect($component['updates'])->toBe(['isAdmin' => true])
        ->and($component['calls'])->toBe([['method' => 'destroy', 'params' => [1]]]);
});

it('counts every component the request carried', function () {
    strikeRequest(
        requestWith([component('one'), component('two'), component('three')]),
        new CorruptComponentPayloadException,
    );

    expect(Ban::sole()->components())->toHaveCount(3);
});

it('survives a snapshot that is not decodable json', function () {
    $request = requestWith([['snapshot' => '{not json at all', 'updates' => ['x' => 1], 'calls' => []]]);

    strikeRequest($request, new CorruptComponentPayloadException);

    $component = Ban::sole()->components()[0];

    expect($component['name'])->toBeNull()
        ->and($component['updates'])->toBe(['x' => 1]);
});

it('survives a request carrying no components at all', function () {
    strikeRequest(
        Request::create('/livewire/update', 'POST', server: ['REMOTE_ADDR' => SUSPECT_IP]),
        new CorruptComponentPayloadException,
    );

    expect(Ban::sole()->components())->toBe([])
        ->and(Ban::sole()->component)->toBeNull();
});

it('shows the targeted property in the alert email', function () {
    strikeRequest(
        requestWith([component('support-chat', updates: ['error' => []])]),
        new CannotUpdateLockedPropertyException('error'),
    );

    $ban = Ban::sole();

    expect($ban->targetedProperty())->toBe('error')
        ->and((new IpBanned($ban))->render())
        ->toContain('Targeted property')
        ->toContain('error');
});
