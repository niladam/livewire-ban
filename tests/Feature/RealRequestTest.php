<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Models\Ban;

/**
 * The routing pipeline converts an exception into a response before it can
 * reach outer middleware, so detection has to be exercised through a genuine
 * request rather than by calling the middleware with a throwing closure.
 */
beforeEach(function () {
    Route::middleware('web')->get('/boom', fn () => throw new CorruptComponentPayloadException);
    Route::middleware('web')->get('/fine', fn () => 'ok');
});

it('records a strike for an exception thrown inside a real request', function () {
    $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/boom');

    expect(Ban::count())->toBe(0)
        ->and(LivewireBan::banned(SUSPECT_IP))->toBeFalse();

    $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/boom');
    $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/boom');

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue()
        ->and(Ban::sole()->exception_class)->toBe(CorruptComponentPayloadException::class);
});

it('forbids the next real request once banned', function () {
    foreach (range(1, 3) as $ignored) {
        $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/boom');
    }

    $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/fine')->assertForbidden();
});

it('leaves an untriggered exception alone', function () {
    Route::middleware('web')->get('/bug', fn () => throw new RuntimeException('a real bug'));

    foreach (range(1, 5) as $ignored) {
        $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/bug');
    }

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});
