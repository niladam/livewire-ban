<x-mail::message>
# IP address banned

@if ($ban->isManual())
**{{ $ban->ip }}** has been banned.
@else
**{{ $ban->ip }}** triggered {{ $ban->strikes }} suspicious Livewire exceptions and has been banned automatically.
@endif

@if ($ban->offence > 1)
This is ban number **{{ $ban->offence }}** for this address in the last 24 hours.
@endif

<x-mail::table>
| | |
|:---|:---|
| Banned at | {{ $ban->banned_at->format('Y-m-d H:i:s') }} |
| Expires at | {{ $ban->isPermanent() ? 'never — this ban is permanent' : $ban->expires_at->format('Y-m-d H:i:s') }} |
| Exception | {{ $ban->isManual() ? 'banned by hand' : '`'.class_basename($ban->exception_class).'`' }} |
| Message | {{ $ban->exception_message }} |
| Component | {{ $ban->component ?? '—' }} |
| Targeted property | {{ $ban->targetedProperty() ?? '—' }} |
| URL | {{ $ban->url() ?? '—' }} |
| User agent | {{ $ban->userAgent() ?? '—' }} |
| Country | {{ $ban->cf_country ?? '—' }} |
| CF-Ray | {{ $ban->cfRay() ?? '—' }} |
</x-mail::table>

@if ($ban->mismatchesCloudflareIp())
<x-mail::panel>
**Heads up:** the `CF-Connecting-IP` header ({{ $ban->connectingIp() }}) disagrees with the connecting address ({{ $ban->ip }}).
Someone is reaching the origin without passing through Cloudflare, or forging the header.
</x-mail::panel>
@endif

@if (filled($ban->components()))
**Livewire payload**

<pre>{!! e(json_encode($ban->components(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !!}</pre>
@endif

<x-mail::button :url="$unbanUrl" color="error">
Lift this ban
</x-mail::button>

The link is signed and expires in {{ config('livewire-ban.alerts.unban_link_days') }} days.
</x-mail::message>
