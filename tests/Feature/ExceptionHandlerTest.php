<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\LivewireBanServiceProvider;
use Niladam\LivewireBan\Tests\Fixtures\ApplicationHandler;
use Niladam\LivewireBan\Tests\Fixtures\ReportSpy;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Orchestra\Testbench\Pest\defineWebRoutes;

defineEnvironment(function ($app) {
    ApplicationHandler::flush();
    ReportSpy::flush();

    $app['config']->set('logging.default', 'null');

    $app->singleton(ExceptionHandlerContract::class, ApplicationHandler::class);

    $app->afterResolving(Handler::class, static function (Handler $handler): void {
        $handler->reportable(static fn (Throwable $e) => ReportSpy::record($e));
    });
});

// A trigger defines a report() of its own, which Laravel honours ahead of
// anything the application registered, so /bug carries an ordinary exception.
defineWebRoutes(function (Router $router) {
    $router->get('/boom', fn () => throw new CorruptComponentPayloadException);
    $router->get('/bug', fn () => throw new RuntimeException('a real bug'));
});

function spray(int $times = 3): void
{
    foreach (range(1, $times) as $ignored) {
        test()->withServerVariables(['REMOTE_ADDR' => SUSPECT_IP])->get('/boom');
    }
}

it('runs the report callback the application registered', function () {
    $this->get('/bug');

    expect(ReportSpy::classes())->toBe([RuntimeException::class]);
});

it('leaves the handler the application bound as the one reporting', function () {
    $this->get('/bug');

    expect(ApplicationHandler::$seen)->toBe([RuntimeException::class]);
});

it('detects without costing the application its configuration', function () {
    spray();

    // withServerVariables sticks, and the banned address is refused at the middleware.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->get('/bug');

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue()
        ->and(ReportSpy::classes())->toBe([RuntimeException::class])
        ->and(ApplicationHandler::$seen)->toContain(CorruptComponentPayloadException::class);
});

it('charges one strike for one exception when the provider boots twice', function () {
    Config::set('livewire-ban.strikes', 2);

    $this->app->register(LivewireBanServiceProvider::class, force: true);

    spray(1);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeFalse();

    spray(1);

    expect(LivewireBan::banned(SUSPECT_IP))->toBeTrue();
});
