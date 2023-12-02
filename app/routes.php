<?php

declare(strict_types=1);

$app->group('/auth', function() {
    $prefix = 'auth';

    $this->redirect('', '/auth/start', 302);
    $this->get('/start', 'AuthController:start')
        ->setName($prefix . '_start');
    $this->get('/callback', 'AuthController:callback')
        ->setName($prefix . '_callback');
    $this->get('/reset', 'AuthController:reset')
        ->setName($prefix . '_reset');
    $this->map(['GET', 'POST'], '/re-authorize', 'AuthController:re_authorize')
        ->setName($prefix . '_re_authorize');
});

$app->get('/', 'PageController:index')
    ->setName('index');
$app->get('/about', 'PageController:about')
    ->setName('about');
$app->get('/documentation', 'PageController:documentation')
    ->setName('documentation');
$app->get('/updates', 'PageController:updates')
    ->setName('updates');

$app->map(['GET', 'POST'], '/new', 'IbcController:new')
    ->setName('new');
$app->map(['GET', 'POST'], '/retry[/{entry_id}]', 'IbcController:retry')
    ->setName('retry');
$app->map(['GET', 'POST'], '/delete[/{id}]', 'IbcController:delete')
    ->setName('delete');
$app->get('/export', 'UsersController:export')
    ->setName('export');
$app->get('/isbn/{isbn:\d+}', 'IbcController:isbn')
    ->setName('isbn');

$app->group('/settings', function() {
    $prefix = 'settings';

    $this->get('', 'UsersController:settings')
        ->setName($prefix);
    $this->post('/update', 'UsersController:settings_update')
        ->setName($prefix . '_update');
});

$app->get('/signout', 'AuthController:signout')
    ->setName('signout');

$app->group('/users', function() {
    $prefix = 'users';

    $this->get('/{domain:[a-zA-Z0-9\.-]+\.[a-z]+}', 'UsersController:profile')
        ->setName('profile');
    $this->get('/{domain:[a-zA-Z0-9\.-]+\.[a-z]+}/{entry:\d+}', 'UsersController:entry')
        ->setName('entry');
});

$app->get('/review/{year}', 'IbcController:review')
    ->setName('review');

