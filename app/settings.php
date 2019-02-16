<?php
return [
    'settings' => [
    	'version' => '0.0.2',
    	'offline' => false,
    	'developer_ip' => '127.0.0.1',
    	'developer_domain' => '',
        'displayErrorDetails' => (getenv('APP_ENV') !== 'production'),
        'determineRouteBeforeAppMiddleware' => true,
        'theme' => [
            'theme_path' => __DIR__ . '/../themes/',
        ]
    ],
];

