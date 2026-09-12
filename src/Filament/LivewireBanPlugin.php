<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Niladam\LivewireBan\Filament\Resources\LivewireBans\BanResource;

class LivewireBanPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'livewire-ban';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([BanResource::class]);
    }

    public function boot(Panel $panel): void {}
}
