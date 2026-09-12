<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Http\Middleware\BanLivewireBots;
use Niladam\LivewireBan\Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in(__DIR__);

// Hooks are static, so one test's registration would otherwise leak into the next.
pest()->beforeEach(fn () => LivewireBan::flushHooks())->in(__DIR__);

const SUSPECT_IP = '203.0.113.9';

function livewireRequest(string $ip = SUSPECT_IP): Request
{
    return Request::create('/livewire/update', 'POST', server: ['REMOTE_ADDR' => $ip]);
}

/**
 * A snapshot shaped the way Livewire posts it, so the recorded payload has
 * something real to summarise.
 *
 * @param  array<string, mixed>  $updates
 */
function tamperedRequest(string $ip = SUSPECT_IP, string $component = 'checkout', array $updates = ['isAdmin' => true]): Request
{
    return Request::create('/livewire/update', 'POST', [
        'components' => [[
            'snapshot' => json_encode(['data' => [], 'memo' => ['name' => $component]]),
            'updates' => $updates,
            'calls' => [],
        ]],
    ], server: ['REMOTE_ADDR' => $ip]);
}

function strike(int $times, string $ip = SUSPECT_IP, ?Throwable $e = null): void
{
    foreach (range(1, $times) as $ignored) {
        LivewireBan::strike(
            tamperedRequest($ip),
            $e ?? new CorruptComponentPayloadException,
        );
    }
}

/**
 * Drive the middleware the way the framework does, so the trigger list decides
 * whether the exception counts. LivewireBan::strike() on its own always counts.
 */
function throughMiddleware(Throwable $e, int $times, string $ip = SUSPECT_IP): void
{
    $middleware = app(BanLivewireBots::class);

    foreach (range(1, $times) as $ignored) {
        try {
            $middleware->handle(tamperedRequest($ip), fn () => throw $e);
        } catch (Throwable) {
            // The middleware must always rethrow; swallowing keeps the loop going.
        }
    }
}
