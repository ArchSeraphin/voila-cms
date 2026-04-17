<?php
declare(strict_types=1);

return [
    'transport' => env('MAIL_TRANSPORT', 'null'),
    'host'      => env('MAIL_HOST', 'localhost'),
    'port'      => (int)env('MAIL_PORT', 587),
    'username'  => env('MAIL_USERNAME', ''),
    'password'  => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@localhost'),
        'name'    => env('MAIL_FROM_NAME', 'voila-cms'),
    ],
];
