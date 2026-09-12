<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Niladam\LivewireBan\Models\Ban;

class IpBanned extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Ban $ban) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[%s] Livewire ban: %s', Config::string('app.name'), $this->ban->ip),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'livewire-ban::mail.banned',
            with: ['unbanUrl' => $this->unbanUrl()],
        );
    }

    private function unbanUrl(): string
    {
        return URL::temporarySignedRoute(
            'livewire-ban.unban',
            now()->addDays(Config::integer('livewire-ban.alerts.unban_link_days', 7)),
            ['ban' => $this->ban->getKey()],
        );
    }
}
