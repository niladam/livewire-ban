<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Events\Struck;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Tests\Fixtures\ReportSpy;
use Niladam\LivewireBan\Tests\TestCase;
use RuntimeException;
use Throwable;

/**
 * Counting a strike happens while an exception is already unwinding, so
 * whatever it costs, it cannot cost the visitor the response they were owed.
 */
class DetectionFailureTest extends TestCase
{
    use RefreshDatabase;

    private const SUSPECT = '203.0.113.46';

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

        $app['config']->set('app.debug', false);
        $app['config']->set('logging.default', 'null');
        $app['config']->set('livewire-ban.strikes', 1);
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/boom', fn () => throw new CorruptComponentPayloadException);
    }

    public function test_a_listener_that_throws_leaves_the_response_alone(): void
    {
        Event::listen(Struck::class, fn () => throw new RuntimeException('listener blew up'));

        $this->withServerVariables(['REMOTE_ADDR' => self::SUSPECT])
            ->get('/boom')
            ->assertStatus(419);
    }

    public function test_an_unreachable_table_leaves_the_response_alone(): void
    {
        Schema::drop('livewire_bans');

        $this->withServerVariables(['REMOTE_ADDR' => self::SUSPECT])
            ->get('/boom')
            ->assertStatus(419);

        $this->assertFalse(LivewireBan::banned(self::SUSPECT));
    }

    public function test_the_failure_is_reported_rather_than_swallowed(): void
    {
        Event::listen(Struck::class, fn () => throw new RuntimeException('listener blew up'));

        $this->withServerVariables(['REMOTE_ADDR' => self::SUSPECT])->get('/boom');

        $this->assertSame([RuntimeException::class], ReportSpy::classes());
    }
}
