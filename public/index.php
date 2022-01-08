<?php

declare(strict_types=1);

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
require dirname(__DIR__) . '/app/routes.php';

$app->run();

