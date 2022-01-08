<?php

declare(strict_types=1);

use Dotenv\Dotenv;

try {
    // Load environment variables from .env file when not in production.
    $dotenv = new Dotenv(dirname(__DIR__));

    if (getenv('APP_ENV') !== 'production') {
        $dotenv->load();
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
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

ini_set('session.name', 'indiebookclub');
ini_set('session.auto_start', '0');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_domain', getenv('IBC_HOSTNAME'));
ini_set('session.cookie_path', '/');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_lifetime', '0');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cache_expire', '30');
ini_set('session.sid_length', '48');
ini_set('session.sid_bits_per_character', '6');
ini_set('session.cache_limiter', 'nocache');
session_start();

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

