<?php
return [
    'settings' => [
    	'version' => '0.0.3',
    	'offline' => false,
    	'developer_ip' => '127.0.0.1',
    	'developer_domain' => 'gregorlove.com',
        'displayErrorDetails' => (getenv('APP_ENV') !== 'production'),
        'determineRouteBeforeAppMiddleware' => true,
        'theme' => [
            'theme_path' => __DIR__ . '/../themes/',
        ]
    ],
];

