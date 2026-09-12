<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Events\Struck;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Tests\Fixtures\ReportSpy;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Orchestra\Testbench\Pest\defineWebRoutes;

defineEnvironment(function ($app) {
    ReportSpy::flush();

    $app['config']->set('app.debug', false);
    $app['config']->set('logging.default', 'null');
    $app['config']->set('livewire-ban.strikes', 1);

    $app->singleton(ExceptionHandlerContract::class, Handler::class);

    $app->afterResolving(Handler::class, static function (Handler $handler): void {
        $handler->reportable(static fn (Throwable $e) => ReportSpy::record($e));
    });
});

defineWebRoutes(function (Router $router) {
    $router->get('/boom', fn () => throw new CorruptComponentPayloadException);
});

it('leaves the response alone when a listener throws', function () {
    Event::listen(Struck::class, fn () => throw new RuntimeException('listener blew up'));

    $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])
        ->get('/boom')
        ->assertStatus(419);
});

it('leaves the response alone when the table is unreachable', function () {
    Schema::drop('livewire_bans');

    $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])
        ->get('/boom')
        ->assertStatus(419);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();
});

it('reports the failure rather than swallowing it', function () {
    Event::listen(Struck::class, fn () => throw new RuntimeException('listener blew up'));

    $this->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/boom');

    expect(ReportSpy::classes())->toBe([RuntimeException::class]);
});
