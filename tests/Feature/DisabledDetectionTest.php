<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Exceptions\DetectingExceptionHandler;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Models\Ban;
use Niladam\LivewireBan\Tests\TestCase;

/**
 * The kill switch is the way out for an application that wants its handler
 * left alone, so it has to mean the handler is never touched at all, not that
 * a layer sits there doing nothing.
 */
class DisabledDetectionTest extends TestCase
{
    use RefreshDatabase;

    private const string SUSPECT = '203.0.113.45';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('livewire-ban.enabled', false);
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/boom', fn () => throw new CorruptComponentPayloadException);
    }

    public function test_the_handler_is_left_as_the_application_had_it(): void
    {
        $this->assertNotInstanceOf(
            DetectingExceptionHandler::class,
            $this->app->make(ExceptionHandlerContract::class),
        );
    }

    public function test_nothing_is_detected(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->withServerVariables(['REMOTE_ADDR' => self::SUSPECT])->get('/boom');
        }

        $this->assertFalse(LivewireBan::banned(self::SUSPECT));
        $this->assertSame(0, Ban::count());
    }
}
