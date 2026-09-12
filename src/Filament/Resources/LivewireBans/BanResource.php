<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Filament\Resources\LivewireBans;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Niladam\LivewireBan\Facades\LivewireBan;
use Niladam\LivewireBan\Models\Ban;
use UnitEnum;

class BanResource extends Resource
{
    protected static ?string $slug = 'livewire-bans';

    /** @return class-string<Ban> */
    public static function getModel(): string
    {
        return LivewireBan::model();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Config::get('livewire-ban.filament.navigation_icon', 'heroicon-o-shield-exclamation');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return Config::get('livewire-ban.filament.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return Config::get('livewire-ban.filament.navigation_sort');
    }

    public static function getCluster(): ?string
    {
        return Config::get('livewire-ban.filament.cluster');
    }

    public static function getModelLabel(): string
    {
        return __('livewire-ban::livewire-ban.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('livewire-ban::livewire-ban.plural_model_label');
    }

    /** Falls through to Filament's policy check when no callback is configured. */
    public static function canAccess(): bool
    {
        $callback = Config::get('livewire-ban.filament.authorize');

        return blank($callback)
            ? parent::canAccess()
            : (bool) app()->call($callback);
    }

    /** Bans are written by the detector, never by hand. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) LivewireBan::query()->active()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return LivewireBan::query()->active()->exists() ? 'danger' : 'gray';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('livewire-ban::livewire-ban.sections.ban'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('ip')->label(__('livewire-ban::livewire-ban.fields.ip'))->copyable(),
                    TextEntry::make('strikes')->label(__('livewire-ban::livewire-ban.fields.strikes')),
                    TextEntry::make('offence')->label(__('livewire-ban::livewire-ban.fields.offence')),
                    TextEntry::make('banned_at')->label(__('livewire-ban::livewire-ban.fields.banned_at'))->dateTime(),
                    TextEntry::make('expires_at')
                        ->label(__('livewire-ban::livewire-ban.fields.expires_at'))
                        ->dateTime()
                        ->placeholder(__('livewire-ban::livewire-ban.permanent')),
                    TextEntry::make('unbanned_at')
                        ->label(__('livewire-ban::livewire-ban.fields.unbanned_at'))
                        ->dateTime()
                        ->placeholder('—'),
                ]),
            Section::make(__('livewire-ban::livewire-ban.sections.request'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('exception_class')
                        ->label(__('livewire-ban::livewire-ban.fields.exception'))
                        ->placeholder(__('livewire-ban::livewire-ban.manual'))
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? class_basename($state) : null),
                    TextEntry::make('component')->label(__('livewire-ban::livewire-ban.fields.component'))->placeholder('—'),
                    TextEntry::make('page')
                        ->label(__('livewire-ban::livewire-ban.fields.page'))
                        ->placeholder('—')
                        ->state(fn (Ban $record): ?string => $record->originPath()),
                    TextEntry::make('target')
                        ->label(__('livewire-ban::livewire-ban.fields.target'))
                        ->placeholder('—')
                        ->state(fn (Ban $record): ?string => $record->targetedProperty()),
                    TextEntry::make('exception_message')
                        ->label(__('livewire-ban::livewire-ban.fields.message'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                    TextEntry::make('url')
                        ->label(__('livewire-ban::livewire-ban.fields.url'))
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->state(fn (Ban $record): ?string => $record->url()),
                    TextEntry::make('user_agent')
                        ->label(__('livewire-ban::livewire-ban.fields.user_agent'))
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->state(fn (Ban $record): ?string => $record->userAgent()),
                    TextEntry::make('cf_country')->label(__('livewire-ban::livewire-ban.fields.country'))->placeholder('—'),
                    TextEntry::make('cf_ray')
                        ->label('CF-Ray')
                        ->placeholder('—')
                        ->state(fn (Ban $record): ?string => $record->cfRay()),
                    TextEntry::make('cf_connecting_ip')
                        ->label('CF-Connecting-IP')
                        ->placeholder('—')
                        ->state(fn (Ban $record): ?string => $record->connectingIp())
                        ->color(fn (Ban $record): ?string => $record->mismatchesCloudflareIp() ? 'danger' : null)
                        ->helperText(fn (Ban $record): ?string => $record->mismatchesCloudflareIp()
                            ? __('livewire-ban::livewire-ban.cloudflare_mismatch')
                            : null),
                ]),
            Section::make(__('livewire-ban::livewire-ban.sections.payload'))
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('components')
                        ->hiddenLabel()
                        ->html()
                        ->columnSpanFull()
                        ->state(fn (Ban $record): string => sprintf(
                            '<pre style="white-space:pre-wrap;word-break:break-word;font-size:0.75rem;line-height:1.5;margin:0;">%s</pre>',
                            e((string) json_encode(
                                $record->components(),
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                            )),
                        )),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('banned_at', 'desc')
            ->columns([
                TextColumn::make('ip')->label(__('livewire-ban::livewire-ban.fields.ip'))->searchable()->copyable(),
                TextColumn::make('exception_class')
                    ->label(__('livewire-ban::livewire-ban.fields.exception'))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? class_basename($state) : __('livewire-ban::livewire-ban.manual'))
                    ->badge()
                    ->color('danger'),
                TextColumn::make('component')->label(__('livewire-ban::livewire-ban.fields.component'))->placeholder('—')->searchable(),
                TextColumn::make('target')
                    ->label(__('livewire-ban::livewire-ban.fields.target'))
                    ->placeholder('—')
                    ->state(fn (Ban $record): ?string => $record->targetedProperty()),
                TextColumn::make('cf_country')->label(__('livewire-ban::livewire-ban.fields.country'))->placeholder('—'),
                TextColumn::make('strikes')->label(__('livewire-ban::livewire-ban.fields.strikes'))->alignCenter(),
                TextColumn::make('offence')->label(__('livewire-ban::livewire-ban.fields.offence'))->alignCenter(),
                TextColumn::make('banned_at')->label(__('livewire-ban::livewire-ban.fields.banned_at'))->dateTime()->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('livewire-ban::livewire-ban.fields.expires_at'))
                    ->dateTime()
                    ->placeholder(__('livewire-ban::livewire-ban.permanent'))
                    ->sortable(),
                TextColumn::make('unbanned_at')->label(__('livewire-ban::livewire-ban.fields.unbanned_at'))->dateTime()->placeholder('—'),
            ])
            ->filters([
                Filter::make('active')
                    ->label(__('livewire-ban::livewire-ban.filters.active'))
                    ->query(fn (Builder $query): Builder => $query->active()),
                SelectFilter::make('exception_class')
                    ->label(__('livewire-ban::livewire-ban.fields.exception'))
                    ->options(fn (): array => collect(Config::array('livewire-ban.triggers', []))
                        ->mapWithKeys(fn (string $class): array => [$class => class_basename($class)])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('unban')
                    ->label(__('livewire-ban::livewire-ban.actions.unban'))
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Ban $record): string => __('livewire-ban::livewire-ban.actions.unban_confirm', ['ip' => $record->ip]))
                    ->visible(fn (Ban $record): bool => is_null($record->unbanned_at))
                    ->action(function (Ban $record): void {
                        LivewireBan::unban($record, Auth::user());

                        Notification::make()
                            ->title(__('livewire-ban::livewire-ban.actions.unbanned', ['ip' => $record->ip]))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBans::route('/'),
        ];
    }
}
