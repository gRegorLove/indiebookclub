<?php
use \App\Controller\AuthController;
use \App\Controller\IbcController;
use \App\Controller\PageController;
use \App\Controller\UsersController;
use \App\Helper\Utils;
use \Coreorm\Slim3\Theme;
use \Monolog\Handler\StreamHandler;
use \Monolog\Logger;

ORM::configure('mysql:host=' . getenv('IBC_DB_HOST') . ';dbname=' . getenv('IBC_DB_NAME'));
ORM::configure('username', getenv('IBC_DB_USERNAME'));
ORM::configure('password', getenv('IBC_DB_PASSWORD'));
ORM::configure('driver_options', [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']);

$container = $app->getContainer();

$logger = new Logger('ibc');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/ibc.log', Logger::DEBUG));
$container['logger'] = $logger;

$container['utils'] = function ($c) {
    return new Utils($c->get('router'));
};

$container['theme'] = function ($c) {
    $settings = $c->get('settings')['theme'];
    $theme = Theme::instance($settings['theme_path']);
    $theme->setLayout('default')
    	->setData('title', 'indiebookclub')
        ->setData('utils', $c->get('utils'));
    return $theme;
};

$container['AuthController'] = function ($c) {
	return new AuthController($c);
};

$container['PageController'] = function ($c) {
	return new PageController($c);
};

$container['IbcController'] = function ($c) {
	return new IbcController($c);
};

$container['UsersController'] = function ($c) {
	return new UsersController($c);
};

