<?php

use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Enums\BlockScope;
use Niladam\LivewireBan\Models\Ban;

use function Illuminate\Support\days;
use function Illuminate\Support\hours;
use function Illuminate\Support\minutes;
use function Illuminate\Support\months;
use function Illuminate\Support\weeks;

return [

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | When this option is disabled nothing is detected and nothing is enforced.
    | Bans already recorded stay in the table as a record of what happened, but
    | no address is turned away and no new strike is counted. Your exception
    | handler is left exactly as it was, so this is also the way out if you
    | would rather nothing wrapped it at all.
    |
    */

    'enabled' => (bool) env('LIVEWIRE_BAN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Middleware Registration
    |--------------------------------------------------------------------------
    |
    | The middleware adds itself to the front of the global stack, so a banned
    | address is turned away before your application does any work on its
    | behalf. You may disable this if you would rather place it yourself.
    |
    */

    'register_middleware' => true,

    /*
    |--------------------------------------------------------------------------
    | Ban Scope
    |--------------------------------------------------------------------------
    |
    | This option determines what a banned address is refused. "Site" refuses
    | every request, while "Livewire" refuses only the Livewire endpoints and
    | leaves the rest of your application reachable, which matters when a
    | single shared or carrier-NAT address stands in for many people.
    |
    | Supported: BlockScope::Site, BlockScope::Livewire
    |
    */

    'block' => BlockScope::Site,

    /*
    |--------------------------------------------------------------------------
    | Suspicious Exceptions
    |--------------------------------------------------------------------------
    |
    | An exception listed here is taken as evidence, since no legitimate
    | browser session produces one. Matching uses "instanceof", so a base class
    | covers everything beneath it, and any exception that escapes your
    | middleware stack may be listed, including your own.
    |
    | Beware of exceptions a real visitor can cause: ValidationException would
    | ban your own users. ComponentNotFoundException is absent for the same
    | reason, as it comes from a broken @livewire() include and would punish
    | whoever happened to load that page.
    |
    */

    'triggers' => [
        CorruptComponentPayloadException::class,
        CannotUpdateLockedPropertyException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Strikes
    |--------------------------------------------------------------------------
    |
    | An address is banned once it reaches this many strikes inside the window
    | below. Counting rather than banning on sight means a stale browser tab or
    | a bad deployment costs a strike instead of locking somebody out. Set the
    | strikes to one to ban on the first offence.
    |
    */

    'strikes' => 3,

    'window' => minutes(10),

    /*
    |--------------------------------------------------------------------------
    | Escalation
    |--------------------------------------------------------------------------
    |
    | How long a ban lasts, indexed by how many bans the address has already
    | earned in the last day. The final entry repeats once an address has
    | exhausted the list, and a null entry never expires at all.
    |
    */

    'escalation' => [
        hours(1),   // 1st offense
        hours(6),   // 2nd offense
        days(1),    // 3rd offense
        weeks(1),   // 4th offense
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowlist
    |--------------------------------------------------------------------------
    |
    | Addresses and CIDR ranges listed here are never struck, whatever they do.
    | Your office, your monitoring, and anything running load tests belong here
    | so that a false positive cannot take them out.
    |
    */

    'allowlist' => array_values(array_filter(
        array_map(trim(...), explode(',', (string) env('LIVEWIRE_BAN_ALLOWLIST', '')))
    )),

    /*
    |--------------------------------------------------------------------------
    | Exempt Roles
    |--------------------------------------------------------------------------
    |
    | Authenticated requests from a user holding one of these roles are never
    | struck, so a bad deployment cannot lock your own team out.
    |
    | The two options below are read together: the second names the method to
    | ask, and the first is handed to it. Listing ['admin', 'owner'] here means
    | every request is decided by the result of:
    |
    |     $user->hasAnyRole(['admin', 'owner'])
    |
    | Naming the method instead of calling one directly is what keeps any
    | particular permissions package from being a requirement. The default
    | suits spatie/laravel-permission; name your own if you use a different
    | convention.
    |
    | Nothing is asked at all when the role list is empty, when nobody is
    | logged in, or when your user model has no such method.
    |
    */

    'exempt_roles' => [],

    'exempt_roles_via' => 'hasAnyRole',

    /*
    |--------------------------------------------------------------------------
    | Custom Exemption
    |--------------------------------------------------------------------------
    |
    | For anything the options above cannot express, name a callable here as
    | [Class::class, 'method'] or an invokable class string. It receives the
    | request and returns true to let it pass unstruck.
    |
    | A closure cannot be written here, since config caching cannot hold one.
    | Register those with LivewireBan::exemptUsing() from a service provider.
    |
    */

    'exempt_using' => null,

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    |
    | One queued mail is sent for each ban. The recipient falls back to your
    | application's default from address. The hourly ceiling exists because a
    | distributed attack must not be able to bury the very inbox that is meant
    | to warn you about it.
    |
    | Every alert carries a signed link that lifts the ban, good for the number
    | of days given here.
    |
    */

    'alerts' => [
        'enabled' => true,
        'to' => env('LIVEWIRE_BAN_ALERT_EMAIL'),
        'per_hour' => 10,
        'unban_link_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Unban Route
    |--------------------------------------------------------------------------
    |
    | Where the signed link in the alert mail points. The signature is the
    | credential and the only thing the route can do is remove a block, so no
    | session is required to follow it from wherever you read your mail.
    |
    */

    'route' => [
        'prefix' => 'livewire-ban',
        'middleware' => ['web', 'signed'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Resource
    |--------------------------------------------------------------------------
    |
    | These options apply only once the plugin is registered on a panel. The
    | authorization callable is given the same way as the exemption above, and
    | leaving it null falls through to Filament's own policy check.
    |
    */

    'filament' => [
        'cluster' => null,
        'navigation_group' => null,
        'navigation_sort' => null,
        'navigation_icon' => 'heroicon-o-shield-exclamation',
        'authorize' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The table holding the bans and the model reading it. Your own model may
    | be named here so long as it extends the one below. The cache store backs
    | the per-request lookup and the strike counters; null uses your default.
    |
    */

    'table' => 'livewire_bans',

    'model' => Ban::class,

    'cache_store' => null,

    /*
    |--------------------------------------------------------------------------
    | Authorization Policy
    |--------------------------------------------------------------------------
    |
    | Laravel cannot discover a policy for a model that lives inside a package,
    | so name yours here and it will be registered for you. It is bound to the
    | base model, which means a replacement model inherits it.
    |
    */

    'policy' => null,

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Bans outlive the block itself, as a record of what happened. Anything
    | older than this is removed by Laravel's "model:prune" command, which you
    | will need to schedule. Null keeps every row forever.
    |
    */

    'prune_after' => months(6),

];
