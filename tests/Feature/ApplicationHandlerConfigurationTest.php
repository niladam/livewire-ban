<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Tests\Fixtures\ReportSpy;
use Niladam\LivewireBan\Tests\TestCase;
use RuntimeException;
use Throwable;

/**
 * An application configures its handler through withExceptions(), which never
 * touches the handler directly: it parks the configuration on an
 * after-resolving callback keyed to Laravel's concrete handler class. The
 * container fires those callbacks against whatever object resolution ended
 * with, so a wrapper installed by an extender leaves the lot unfired —
 * reportable(), renderable(), throttle(), dontReport(), all of it, silently.
 *
 * Written as a class because the callback has to be registered before the
 * service provider boots, which is the order a real bootstrap/app.php has.
 */
class ApplicationHandlerConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private const string SUSPECT = '203.0.113.41';

    private const string INNOCENT = '198.51.100.7';

    protected function resolveApplicationExceptionHandler($app): void
    {
        ReportSpy::flush();

        $app->singleton(ExceptionHandlerContract::class, Handler::class);

        $app->afterResolving(Handler::class, static function (Handler $handler): void {
            $handler->reportable(static fn (Throwable $e) => ReportSpy::record($e));
        });
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('logging.default', 'null');
    }

    /**
     * The trigger throws at /boom. An ordinary exception throws at /bug,
     * because a trigger defines a report() of its own, which Laravel honours
     * ahead of anything the application registered.
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/boom', fn () => throw new CorruptComponentPayloadException);
        $router->middleware('web')->get('/bug', fn () => throw new RuntimeException('a real bug'));
    }

    public function test_the_applications_own_report_callback_still_runs(): void
    {
        $this->get('/bug');

        $this->assertSame([RuntimeException::class], ReportSpy::classes());
    }

    public function test_detection_does_not_cost_the_application_its_configuration(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->withServerVariables(['REMOTE_ADDR' => self::SUSPECT])->get('/boom');
        }

        // withServerVariables sticks, and the banned address is refused at
        // the middleware before it could throw anything.
        $this->withServerVariables(['REMOTE_ADDR' => self::INNOCENT])->get('/bug');

        $this->assertTrue(LivewireBan::banned(self::SUSPECT));
        $this->assertSame([RuntimeException::class], ReportSpy::classes());
    }
}
