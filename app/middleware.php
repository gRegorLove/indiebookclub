<?php

declare(strict_types=1);

// Maintenance mode
$app->add(function ($request, $response, $next) use ($container) {
    if ($this->get('settings')['offline'] && $_SERVER['REMOTE_ADDR'] != $this->settings['developer_ip']) {
        $response = $response->withStatus(503)->withHeader('Retry-After', 3600);
        return $this->view->render($response, 'pages/maintenance.twig');
    }
    return $next($request, $response);
});

// Route-based access controls
$app->add(function ($request, $response, $next) use ($container) {
    $route = $request->getAttribute('route');

    if (!$route) {
        return $next($request, $response);
    }

    $route_name = $route->getName();

    $authenticated_route_names = [
        'new',
        'delete',
        'settings',
        'settings_update',
        'auth_reset',
        'auth_re_authorize',
    ];

    if (in_array($route_name, $authenticated_route_names) && !array_key_exists('user_id', $_SESSION)) {
        if ($request->isPost()) {
            $response = $response->withStatus(401);
            return $container->view->render($response, 'pages/400.twig', [
                'short_title' => 'Unauthorized',
                'message' => '<p> Please log in </p>',
            ]);
        }

        return $response->withRedirect($this->router->pathFor('index'), 302);
    }

    return $next($request, $response);
});

// Security headers
$app->add(function ($request, $response, $next) {
    $response = $next($request, $response);
    return $response
        ->withHeader('Strict-Transport-Security', 'max-age=10368000; includeSubDomains')
        ->withHeader('X-Frame-Options', 'SAMEORIGIN')
        ->withHeader('X-XSS-Protection', '1; mode=block')
        ->withHeader('X-Content-Type-Options', 'nosniff');
});

// 404 Handler
$app->add(function ($request, $response, $next) use ($container) {
    $response = $next($request, $response);

    if (404 === $response->getStatusCode() && 0 === $response->getBody()->getSize()) {
        $handler = $container['notFoundHandler'];
        return $handler($request, $response);
    }

    return $response;
});


// No trailing slash on URLs
$app->add(function ($request, $response, $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();

    if ($path != '/' && substr($path, -1) == '/') {
        $uri = $uri->withPath(substr($path, 0, -1));
        return $response->withRedirect((string)$uri, 301);
    }

    return $next($request, $response);
});

