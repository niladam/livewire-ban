<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Niladam\LivewireBan\Http\Controllers\UnbanController;

Route::get('{ban}/unban', UnbanController::class)->name('livewire-ban.unban');
