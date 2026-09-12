<?php

declare(strict_types=1);

namespace Niladam\LivewireBan;

use DateInterval;
use Illuminate\Container\Attributes\Config;
use Niladam\LivewireBan\Enums\BlockScope;
use Niladam\LivewireBan\Models\Ban;
use Throwable;

final readonly class Settings
{
    /**
     * @param  list<class-string<Throwable>>  $triggers
     * @param  list<DateInterval|null>  $escalation
     * @param  list<string>  $allowlist
     * @param  list<string>  $exemptRoles
     * @param  class-string<Ban>  $model
     */
    public function __construct(
        #[Config('livewire-ban.enabled', true)]
        public bool $enabled,

        #[Config('livewire-ban.block', BlockScope::Site)]
        public BlockScope $block,

        #[Config('livewire-ban.triggers', [])]
        public array $triggers,

        #[Config('livewire-ban.strikes', 3)]
        public int $strikes,

        #[Config('livewire-ban.window')]
        public DateInterval $window,

        #[Config('livewire-ban.escalation', [])]
        public array $escalation,

        #[Config('livewire-ban.allowlist', [])]
        public array $allowlist,

        #[Config('livewire-ban.exempt_roles', [])]
        public array $exemptRoles,

        #[Config('livewire-ban.exempt_roles_via', 'hasAnyRole')]
        public string $exemptRolesVia,

        #[Config('livewire-ban.exempt_using')]
        public mixed $exemptUsing,

        #[Config('livewire-ban.alerts.enabled', true)]
        public bool $alertsEnabled,

        #[Config('livewire-ban.alerts.to')]
        public ?string $alertsTo,

        #[Config('livewire-ban.alerts.per_hour', 10)]
        public int $alertsPerHour,

        #[Config('livewire-ban.model', Ban::class)]
        public string $model,

        #[Config('livewire-ban.cache_store')]
        public ?string $cacheStore,
    ) {}

}
