<?php

require dirname(__DIR__) . '/vendor/autoload.php';

require dirname(__DIR__) . '/app/config.php';

// Instantiate the app
$settings = require dirname(__DIR__) . '/app/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require dirname(__DIR__) . '/app/dependencies.php';

// Register middleware
require dirname(__DIR__) . '/app/middleware.php';

// Routes
$app->redirect('/auth', '/auth/start', 302);

$app->group('/auth', function() {
    $this->get('/start', 'AuthController:start')->setName('auth_start');
    $this->get('/callback', 'AuthController:callback')->setName('auth_callback');
    $this->get('/reset', 'AuthController:reset')->setName('auth_reset');
});

$app->get('/signout', 'AuthController:signout')->setName('signout');

$app->get('/', 'PageController:index')->setName('index');
$app->get('/about', 'PageController:about')->setName('about');
$app->get('/documentation', 'PageController:documentation')->setName('documentation');

$app->map(['GET', 'POST'], '/new', 'IbcController:new')->setName('new');
$app->get('/isbn/{isbn:\d+}', 'IbcController:isbn')->setName('isbn');

$app->get('/settings', 'UsersController:settings')->setName('settings');
$app->get('/users/{domain:[a-zA-Z0-9\.-]+\.[a-z]+}', 'UsersController:profile')->setName('profile');
$app->get('/users/{domain:[a-zA-Z0-9\.-]+\.[a-z]+}/{entry:\d+}', 'UsersController:entry')->setName('entry');
$app->get('/export', 'UsersController:export')->setName('export');

$app->run();

