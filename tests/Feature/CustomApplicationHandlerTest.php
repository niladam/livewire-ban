<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Tests\Fixtures\ApplicationHandler;
use Niladam\LivewireBan\Tests\Fixtures\ReportSpy;
use Niladam\LivewireBan\Tests\TestCase;
use RuntimeException;
use Throwable;

/**
 * Detection wraps whatever handler the application ended up with. Claiming the
 * binding outright would be the easy fix for the configuration problem and a
 * worse bug: an application that binds a handler of its own would quietly stop
 * using it.
 */
class CustomApplicationHandlerTest extends TestCase
{
    use RefreshDatabase;

    private const string SUSPECT = '203.0.113.42';

    protected function resolveApplicationExceptionHandler($app): void
    {
        ApplicationHandler::flush();
        ReportSpy::flush();

        $app->singleton(ExceptionHandlerContract::class, ApplicationHandler::class);

        $app->afterResolving(Handler::class, static function (Handler $handler): void {
            $handler->reportable(static fn (Throwable $e) => ReportSpy::record($e));
        });
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('logging.default', 'null');
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/boom', fn () => throw new CorruptComponentPayloadException);
        $router->middleware('web')->get('/bug', fn () => throw new RuntimeException('a real bug'));
    }

    public function test_the_handler_the_application_bound_is_still_the_one_reporting(): void
    {
        $this->get('/bug');

        $this->assertSame([RuntimeException::class], ApplicationHandler::$seen);
        $this->assertSame([RuntimeException::class], ReportSpy::classes());
    }

    public function test_detection_still_runs_through_it(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->withServerVariables(['REMOTE_ADDR' => self::SUSPECT])->get('/boom');
        }

        $this->assertTrue(LivewireBan::banned(self::SUSPECT));
        $this->assertSame([CorruptComponentPayloadException::class], array_unique(ApplicationHandler::$seen));
    }
}
