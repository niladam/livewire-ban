<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Tests\Fixtures\RecordingHandler;
use Niladam\LivewireBan\Tests\Fixtures\RecordingHandlerServiceProvider;
use Niladam\LivewireBan\Tests\TestCase;

/**
 * Decorating the handler is not claiming it. Another package doing the same
 * thing — before detection or after it — has to keep working, and so does
 * detection, whichever ends up on top.
 */
class StackedDecoratorTest extends TestCase
{
    use RefreshDatabase;

    private const string SUSPECT = '203.0.113.44';

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), RecordingHandlerServiceProvider::class];
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/boom', fn () => throw new CorruptComponentPayloadException);
    }

    public function test_a_decorator_applied_before_detection_keeps_working(): void
    {
        $this->strikeUntilBanned();

        $this->assertTrue(LivewireBan::banned(self::SUSPECT));
        $this->assertSame([CorruptComponentPayloadException::class], array_unique(RecordingHandler::$rendered));
    }

    public function test_a_decorator_applied_after_detection_keeps_working(): void
    {
        RecordingHandler::flush();

        $this->app->extend(
            ExceptionHandlerContract::class,
            fn (ExceptionHandlerContract $handler): ExceptionHandlerContract => new RecordingHandler($handler),
        );

        $this->strikeUntilBanned();

        $this->assertTrue(LivewireBan::banned(self::SUSPECT));
        $this->assertSame([CorruptComponentPayloadException::class], array_unique(RecordingHandler::$rendered));
    }

    private function strikeUntilBanned(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->withServerVariables(['REMOTE_ADDR' => self::SUSPECT])->get('/boom');
        }
    }
}
