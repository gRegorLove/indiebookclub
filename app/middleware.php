<?php

declare(strict_types=1);

use App\Middleware\AuthorizationMiddleware;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

// Maintenance mode
$app->add(function (ServerRequestInterface $request, ResponseInterface $response, $next) use ($container) {
    if ($this->get('settings')['offline'] && $_SERVER['REMOTE_ADDR'] != $this->settings['developer_ip']) {
        $response = $response->withStatus(503)->withHeader('Retry-After', 3600);
        return $this->view->render($response, 'pages/maintenance.twig');
    }
    return $next($request, $response);
});

## Authorization middleware
$app->add( new AuthorizationMiddleware($container) );

// Security headers
$app->add(function (ServerRequestInterface $request, ResponseInterface $response, $next) {
    $response = $next($request, $response);
    return $response
        ->withHeader('Strict-Transport-Security', 'max-age=10368000; includeSubDomains')
        ->withHeader('X-Frame-Options', 'SAMEORIGIN')
        ->withHeader('X-XSS-Protection', '1; mode=block')
        ->withHeader('X-Content-Type-Options', 'nosniff');
});

// 404 Handler
$app->add(function (ServerRequestInterface $request, ResponseInterface $response, $next) use ($container) {
    $response = $next($request, $response);

    if (404 === $response->getStatusCode() && 0 === $response->getBody()->getSize()) {
        $handler = $container['notFoundHandler'];
        return $handler($request, $response);
    }

    return $response;
});


// No trailing slash on URLs
$app->add(function (ServerRequestInterface $request, ResponseInterface $response, $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();

    if ($path != '/' && substr($path, -1) == '/') {
        $uri = $uri->withPath(substr($path, 0, -1));
        return $response->withRedirect((string)$uri, 301);
    }

    return $next($request, $response);
});

