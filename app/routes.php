<?php

declare(strict_types=1);

$app->redirect('/auth', '/auth/start', 302);

$app->group('/auth', function() {
    $prefix = 'auth';

    $this->get('/debug', 'AuthController:debug')->setName($prefix . '_debug');
    $this->get('/start', 'AuthController:start')->setName($prefix . '_start');
    $this->get('/callback', 'AuthController:callback')->setName($prefix . '_callback');
    $this->get('/reset', 'AuthController:reset')->setName($prefix . '_reset');
    $this->map(['GET', 'POST'], '/re-authorize', 'AuthController:re_authorize')->setName($prefix . '_re_authorize');
});

$app->get('/signout', 'AuthController:signout')->setName('signout');

$app->get('/', 'PageController:index')->setName('index');
$app->get('/about', 'PageController:about')->setName('about');
$app->get('/documentation', 'PageController:documentation')->setName('documentation');
$app->get('/updates', 'PageController:updates')->setName('updates');

$app->map(['GET', 'POST'], '/new', 'IbcController:new')->setName('new');
$app->map(['GET', 'POST'], '/delete[/{id}]', 'IbcController:delete')->setName('delete');
$app->get('/isbn/{isbn:\d+}', 'IbcController:isbn')->setName('isbn');

$app->get('/settings', 'UsersController:settings')->setName('settings');
$app->post('/settings/update', 'UsersController:settings_update')->setName('settings_update');
$app->get('/users/{domain:[a-zA-Z0-9\.-]+\.[a-z]+}', 'UsersController:profile')->setName('profile');
$app->get('/users/{domain:[a-zA-Z0-9\.-]+\.[a-z]+}/{entry:\d+}', 'UsersController:entry')->setName('entry');
$app->get('/export', 'UsersController:export')->setName('export');

