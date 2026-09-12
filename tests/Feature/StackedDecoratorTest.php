<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Routing\Router;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Tests\Fixtures\RecordingHandler;
use Niladam\LivewireBan\Tests\Fixtures\RecordingHandlerServiceProvider;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Orchestra\Testbench\Pest\defineWebRoutes;

defineEnvironment(function ($app) {
    $app->register(RecordingHandlerServiceProvider::class);
});

defineWebRoutes(function (Router $router) {
    $router->get('/boom', fn () => throw new CorruptComponentPayloadException);
});

it('keeps a decorator applied before detection working', function () {
    foreach (range(1, 3) as $ignored) {
        $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/boom');
    }

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue()
        ->and(array_unique(RecordingHandler::$rendered))->toBe([CorruptComponentPayloadException::class]);
});

it('keeps a decorator applied after detection working', function () {
    RecordingHandler::flush();

    $this->app->extend(
        ExceptionHandlerContract::class,
        fn (ExceptionHandlerContract $handler): ExceptionHandlerContract => new RecordingHandler($handler),
    );

    foreach (range(1, 3) as $ignored) {
        $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/boom');
    }

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue()
        ->and(array_unique(RecordingHandler::$rendered))->toBe([CorruptComponentPayloadException::class]);
});
