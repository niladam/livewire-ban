<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Routing\Router;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Exceptions\DetectingExceptionHandler;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Models\Ban;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Orchestra\Testbench\Pest\defineWebRoutes;

defineEnvironment(function ($app) {
    $app['config']->set('livewire-ban.enabled', false);
});

defineWebRoutes(function (Router $router) {
    $router->get('/boom', fn () => throw new CorruptComponentPayloadException);
});

it('leaves the handler as the application had it', function () {
    expect($this->app->make(ExceptionHandlerContract::class))
        ->not->toBeInstanceOf(DetectingExceptionHandler::class);
});

it('detects nothing', function () {
    foreach (range(1, 5) as $ignored) {
        $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/boom');
    }

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse()
        ->and(Ban::count())->toBe(0);
});
