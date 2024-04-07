<?php

namespace App\Middleware;

// use InvalidArgumentException;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};
use Slim\Route;

class AuthorizationMiddleware
{
    private $container;

    private $authenticated_route_names = [
        'new',
        'delete',
        'settings',
        'settings_update',
        'auth_reset',
        'auth_re_authorize',
    ];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $next
    ): ResponseInterface {
        $route = $request->getAttribute('route');
        if (!$route) {
            return $next($request, $response);
        }

        if (strpos($request->getUri()->getPath(), '//') === 0) {
            return $response->withStatus(404);
        }

        if ($this->is_authentication_required($route)) {
            if ($request->isPost()) {
                $response = $response->withStatus(401);
                return $this->container->view->render(
                    $response,
                    'pages/400.twig', [
                        'short_title' => 'Unauthorized',
                        'message' => '<p> Please log in </p>',
                    ]
                );
            }

            $route_name = $route->getName() ?? 'index';
            $params = $this->get_allowlist_params(
                $request->getQueryParams(),
                $route_name
            );

            try {
                # attempt to build the redirect URL
                $redirect = $this->container->router->pathFor(
                    $route_name,
                    $route->getArguments(),
                    $params
                );
            } catch (Exception $e) {
                # otherwise, default path
                $redirect = $this->container->router->pathFor('index');
            }

            $redirect = $this->container->utils
                ->get_redirect($redirect);

            $_SESSION['signin_prompt'] = true;
            $_SESSION['signin_redirect'] = $redirect;

            # http redirect status
            $status = 302;
            if ($request->isPost()) {
                $status = 303;
            }

            return $response->withRedirect(
                $this->container->router->pathFor('index'),
                $status
            );
        }

        return $next($request, $response);
    }

    protected function is_authenticated(): bool
    {
        return (array_key_exists('user_id', $_SESSION) && !is_null($_SESSION['user_id']));
    }

    protected function is_authentication_required(Route $route): bool
    {
        # already authenticated
        if ($this->is_authenticated()) {
            return false;
        }

        $route_name = $route->getName();
        if (in_array($route_name, $this->authenticated_route_names)) {
            return true;
        }

        return false;
    }

    protected function get_allowlist_params(
        array $params,
        string $route_name = 'new'
    ): array {

        if ($route_name == 'new') {
            $allowlist = array_fill_keys([
                'read-status',
                'title',
                'authors',
                'isbn',
                'doi',
                'tags',
            ], '');

            return array_intersect_key(
                $params,
                $allowlist
            );
        }

        return [];
    }
}

