<?php
use Dotenv\Dotenv;

try {
    // Load environment variables from .env file when not in production.
    $dotenv = new Dotenv(dirname(__DIR__));

    if (getenv('APP_ENV') !== 'production') {
        $dotenv->load();
    }

    // Require these environment variables.
    $dotenv->required([
        'IBC_HOSTNAME',
        'IBC_BASE_URL',
        'IBC_DB_HOST',
        'IBC_DB_NAME',
        'IBC_DB_USERNAME',
        'IBC_DB_PASSWORD',
    ]);
} catch (RunTimeException $e) {
    echo $e->getMessage(); exit;
}

define('APP_DIR', dirname(__DIR__));
date_default_timezone_set('UTC');

session_start([
    'name' => 'indiebookclub',
    'cookie_lifetime' => 7 * 24 * 60 * 60,
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_domain' => getenv('IBC_HOSTNAME'),
]);

// Make sure session canary is set.
if (!isset($_SESSION['canary'])) {
    session_regenerate_id(true);
    $_SESSION['canary'] = time();
}

// Regenerate session ID every five minutes.
if ($_SESSION['canary'] < time() - 300) {
    session_regenerate_id(true);
    $_SESSION['canary'] = time();
}

