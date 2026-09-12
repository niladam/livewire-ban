<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Niladam\LivewireBan\Models\Ban;

/** @extends Factory<Ban> */
class BanFactory extends Factory
{
    protected $model = Ban::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ip' => fake()->ipv4(),
            'exception_class' => CorruptComponentPayloadException::class,
            'exception_message' => 'Livewire encountered corrupt data when trying to hydrate a component.',
            'component' => 'checkout',
            'cf_country' => 'RO',
            'context' => [
                'request' => [
                    'url' => 'https://example.test/livewire/update',
                    'user_agent' => 'python-requests/2.31.0',
                    'cf_ray' => fake()->bothify('########????####-OTP'),
                    'cf_connecting_ip' => null,
                ],
                'livewire' => [
                    'target' => null,
                    'components' => [[
                        'name' => 'checkout',
                        'id' => 'aBc12345',
                        'path' => '/',
                        'properties' => ['total'],
                        'updates' => ['isAdmin' => true],
                        'calls' => [],
                    ]],
                ],
            ],
            'strikes' => 3,
            'offence' => 1,
            'banned_at' => now(),
            'expires_at' => now()->addHour(),
        ];
    }

    public function lifted(): static
    {
        return $this->state(fn (): array => ['unbanned_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subHour()]);
    }

    /** A request that reached the origin without passing through Cloudflare. */
    public function offOrigin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'context' => array_replace_recursive($attributes['context'], [
                'request' => ['cf_connecting_ip' => fake()->ipv4()],
            ]),
        ]);
    }

    public function targeting(string $property): static
    {
        return $this->state(fn (array $attributes): array => [
            'context' => array_replace_recursive($attributes['context'], [
                'livewire' => ['target' => $property],
            ]),
        ]);
    }
}
