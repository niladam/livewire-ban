<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Fixtures;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

class RecordingHandlerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RecordingHandler::flush();

        $this->app->extend(
            ExceptionHandler::class,
            fn (ExceptionHandler $handler): ExceptionHandler => new RecordingHandler($handler),
        );
    }
}
