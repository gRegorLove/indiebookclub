<?php

declare(strict_types=1);

use Dotenv\Dotenv;

try {
    ## prepare environment

    # load cached environment only in production
    $cached_env_file = __DIR__ . '/env.php';
    if (file_exists($cached_env_file)) {
        require $cached_env_file;
    } else {
        # otherwise load from .env file
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        ## require these environment variables
        $dotenv->required([
            'APP_ENV',
            'IBC_EMAIL',
            'IBC_HOSTNAME',
            'IBC_BASE_URL',
            'IBC_DB_HOST',
            'IBC_DB_NAME',
            'IBC_DB_USERNAME',
            'IBC_DB_PASSWORD',
            'LOG_DIR',
            'LOG_NAME',
        ]);
    }
} catch (RunTimeException $e) {
    echo $e->getMessage(); exit;
}

define('APP_DIR', dirname(__DIR__));
date_default_timezone_set('UTC');

$session_name = 'indiebookclub';
$app_env = $_ENV['APP_ENV'] ?? 'dev';
if ($app_env !== 'production') {
    $session_name = $app_env . '_' . $session_name;
}
ini_set('session.name', $session_name);
ini_set('session.auto_start', '0');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_domain', $_ENV['IBC_HOSTNAME']);
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

