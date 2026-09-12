<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

class RolelessUser extends User
{
    protected $guarded = [];
}
