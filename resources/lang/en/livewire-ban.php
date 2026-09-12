<?php

declare(strict_types=1);

return [
    'model_label' => 'Banned IP',
    'plural_model_label' => 'Banned IPs',

    'sections' => [
        'ban' => 'Ban',
        'request' => 'Request',
        'payload' => 'Livewire payload',
    ],

    'fields' => [
        'ip' => 'IP',
        'strikes' => 'Exceptions',
        'offence' => 'Ban number',
        'banned_at' => 'Banned',
        'expires_at' => 'Expires',
        'unbanned_at' => 'Unbanned',
        'exception' => 'Exception',
        'component' => 'Component',
        'target' => 'Targeted property',
        'message' => 'Message',
        'url' => 'URL',
        'user_agent' => 'User agent',
        'country' => 'Country',
    ],

    'filters' => [
        'active' => 'Bans still in force',
    ],

    'actions' => [
        'unban' => 'Unban',
        'unban_confirm' => ':ip will be able to reach the site again.',
        'unbanned' => ':ip has been unbanned',
    ],

    'permanent' => 'Permanent',
    'manual' => 'Banned by hand',

    'cloudflare_mismatch' => 'Disagrees with the connecting address — the request bypassed Cloudflare, or the header was forged.',
];
