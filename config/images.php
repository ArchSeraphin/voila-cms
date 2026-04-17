<?php
declare(strict_types=1);

return [
    'max_size_bytes' => 10 * 1024 * 1024, // 10 MB
    'allowed_mime'   => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
    'allowed_ext'    => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
    'presets' => [
        'thumb'   => ['w' => 200,  'h' => 200, 'fit' => 'crop'],
        'card'    => ['w' => 640,                'fit' => 'max'],
        'hero'    => ['w' => 1920,               'fit' => 'max'],
        'gallery' => ['w' => 1280,               'fit' => 'max'],
        'full'    => ['w' => 2560,               'fit' => 'max'],
    ],
    'srcset_widths' => [320, 640, 960, 1280, 1920],
    'default_quality' => 80,
];
