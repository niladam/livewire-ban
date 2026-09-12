<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

class StaffUser extends User
{
    protected $guarded = [];

    /** @param  list<string>  $roles */
    public function hasAnyRole(array $roles): bool
    {
        return (bool) array_intersect($roles, (array) ($this->attributes['roles'] ?? []));
    }

    /** @param  list<string>  $roles */
    public function isOneOf(array $roles): bool
    {
        return $this->hasAnyRole($roles);
    }
}
