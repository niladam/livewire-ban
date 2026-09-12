<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Models;

use Carbon\CarbonInterface;
use DateInterval;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Niladam\LivewireBan\Database\Factories\BanFactory;
use Throwable;

/**
 * @property int $id
 * @property string $ip
 * @property class-string<Throwable>|null $exception_class
 * @property string|null $exception_message
 * @property string|null $component
 * @property string|null $cf_country
 * @property array<string, mixed>|null $context
 * @property int $strikes
 * @property int $offence
 * @property CarbonInterface $banned_at
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $unbanned_at
 * @property int|null $unbanned_by
 */
class Ban extends Model
{
    /** @use HasFactory<BanFactory> */
    use HasFactory;

    use MassPrunable;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'strikes' => 'integer',
            'offence' => 'integer',
            'banned_at' => 'datetime',
            'expires_at' => 'datetime',
            'unbanned_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return Config::string('livewire-ban.table', 'livewire_bans');
    }

    /**
     * Laravel's factory resolver only knows how to strip the application
     * namespace, so a package model has to name its own factory.
     */
    protected static function newFactory(): BanFactory
    {
        return BanFactory::new();
    }

    /**
     * Without a foreign key, because the column has to hold whatever key type
     * the host application's users table happens to use.
     *
     * @return BelongsTo<Model, $this>
     */
    public function unbannedBy(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('auth.providers.users.model');

        return $this->belongsTo($model, 'unbanned_by');
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('unbanned_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** No exception behind it means somebody banned this address by hand. */
    public function isManual(): bool
    {
        return is_null($this->exception_class);
    }

    public function isPermanent(): bool
    {
        return is_null($this->expires_at);
    }

    /** The property the caller went after, when the exception named one. */
    public function targetedProperty(): ?string
    {
        $target = Arr::get($this->context ?? [], 'livewire.target');

        return is_string($target) ? $target : null;
    }

    public function url(): ?string
    {
        return $this->fromRequest('url');
    }

    /** The page the snapshot was lifted from, which is where the attack began. */
    public function originPath(): ?string
    {
        $path = Arr::get($this->context ?? [], 'livewire.components.0.path');

        return is_string($path) ? $path : null;
    }

    public function userAgent(): ?string
    {
        return $this->fromRequest('user_agent');
    }

    public function cfRay(): ?string
    {
        return $this->fromRequest('cf_ray');
    }

    public function connectingIp(): ?string
    {
        return $this->fromRequest('cf_connecting_ip');
    }

    /** @return list<array<string, mixed>> */
    public function components(): array
    {
        return (array) Arr::get($this->context ?? [], 'livewire.components', []);
    }

    private function fromRequest(string $key): ?string
    {
        $value = Arr::get($this->context ?? [], "request.{$key}");

        return is_string($value) ? $value : null;
    }

    /** A disagreement means the request bypassed Cloudflare, or the header was forged. */
    public function mismatchesCloudflareIp(): bool
    {
        return filled($this->connectingIp()) && $this->connectingIp() !== $this->ip;
    }

    /**
     * Bans are an audit trail rather than live state, so old rows mass delete
     * safely. Schedule Laravel's model:prune command to use this.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $after = Config::get('livewire-ban.prune_after');

        return $after instanceof DateInterval
            ? static::query()->where('banned_at', '<', now()->sub($after))
            : static::query()->whereRaw('1 = 0');
    }
}
