<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Enums;

use Illuminate\Http\Request;

enum BlockScope: string
{
    case Site = 'site';

    case Livewire = 'livewire';

    public function covers(Request $request): bool
    {
        return match ($this) {
            self::Site => true,
            self::Livewire => $request->hasHeader('X-Livewire'),
        };
    }
}
