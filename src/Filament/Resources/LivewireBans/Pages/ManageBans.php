<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Filament\Resources\LivewireBans\Pages;

use Filament\Resources\Pages\ManageRecords;
use Niladam\LivewireBan\Filament\Resources\LivewireBans\BanResource;

class ManageBans extends ManageRecords
{
    protected static string $resource = BanResource::class;
}
