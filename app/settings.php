<?php

declare(strict_types=1);

return [
    'settings' => [
        'version' => '0.1.1',
        'offline' => false,
        'developer_ip' => '127.0.0.1',
        'developer_domains' => [
            'example.com',
        ],
        'displayErrorDetails' => ($_ENV['APP_ENV'] !== 'production'),
        'determineRouteBeforeAppMiddleware' => true,
        'theme' => [
            'public_path' => dirname(__DIR__) . '/public/',
            'twig_path' => dirname(__DIR__) . '/templates/',
            'twig_cache_path' => dirname(__DIR__) . '/cache/twig/',
            'enable_twig_cache' => false,
        ]
    ],
];

