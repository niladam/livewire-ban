<?php

declare(strict_types=1);

return [
    'model_label' => 'IP blocat',
    'plural_model_label' => 'IP-uri blocate',

    'sections' => [
        'ban' => 'Blocare',
        'request' => 'Cerere',
        'payload' => 'Payload Livewire',
    ],

    'fields' => [
        'ip' => 'IP',
        'strikes' => 'Excepții',
        'offence' => 'A câta blocare',
        'banned_at' => 'Blocat la',
        'expires_at' => 'Expiră la',
        'unbanned_at' => 'Deblocat la',
        'exception' => 'Excepție',
        'page' => 'Pagina',
        'component' => 'Componentă',
        'target' => 'Proprietatea vizată',
        'message' => 'Mesaj',
        'url' => 'URL',
        'user_agent' => 'User agent',
        'country' => 'Țară',
    ],

    'filters' => [
        'active' => 'Doar blocările în vigoare',
    ],

    'actions' => [
        'unban' => 'Deblochează',
        'unban_confirm' => ':ip va putea accesa din nou site-ul.',
        'unbanned' => ':ip a fost deblocat',
    ],

    'permanent' => 'Permanentă',
    'manual' => 'Blocat manual',

    'cloudflare_mismatch' => 'Nu corespunde cu adresa conexiunii — cererea a ocolit Cloudflare, sau antetul a fost falsificat.',
];
