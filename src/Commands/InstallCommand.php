<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Commands;

use Carbon\CarbonInterval;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Niladam\LivewireBan\Filament\LivewireBanPlugin;
use Niladam\LivewireBan\Warden;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\table;

class InstallCommand extends Command
{
    protected $signature = 'livewire-ban:install {--config : Publish the config file}';

    protected $description = 'Show what livewire-ban is already doing, and what is left to do';

    public function handle(Warden $warden): int
    {
        intro('Livewire Ban');

        $this->callSilently('vendor:publish', ['--tag' => 'livewire-ban-migrations']);
        info('Migration published to database/migrations.');

        if ($this->option('config')) {
            $this->callSilently('vendor:publish', ['--tag' => 'livewire-ban-config']);
            info('Config published to config/livewire-ban.php');
        }

        $settings = $warden->settings;

        table(['', 'Now'], [
            ['Threshold', "{$settings->strikes} strikes in ".$this->humanise($settings->window)],
            ['First ban', $this->humanise($warden->banDuration(1))],
            ['Blocks', $settings->block->value],
            ['Alerts to', $settings->alertsTo ?: Config::string('mail.from.address', 'nowhere — set LIVEWIRE_BAN_ALERT_EMAIL')],
        ]);

        note('Run your migrations to create the bans table.');

        if (class_exists(Panel::class)) {
            note('Filament detected. Add the panel resource with:'.PHP_EOL.'  ->plugin('.LivewireBanPlugin::class.'::make())');
        }

        if (! $this->option('config')) {
            note('Only if you need to change a default: --config publishes the file.');
        }

        outro('The middleware registers itself. Nothing else to wire up.');

        return self::SUCCESS;
    }

    private function humanise(?\DateInterval $interval): string
    {
        return is_null($interval)
            ? 'permanent'
            : CarbonInterval::instance($interval)->forHumans();
    }
}
