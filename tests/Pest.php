<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
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
 * Drive a genuine request that throws, which is the only way detection can be
 * exercised: the routing pipeline turns the exception into a response before
 * any middleware could catch it.
 */
function requestThatThrows(Throwable $e, int $times, string $ip = SUSPECT_IP): void
{
    Route::middleware('web')->get('/livewire-ban-test-throw', fn () => throw $e);

    foreach (range(1, $times) as $ignored) {
        test()->withServerVariables(['REMOTE_ADDR' => $ip])->get('/livewire-ban-test-throw');
    }
}
