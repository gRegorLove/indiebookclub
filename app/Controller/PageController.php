<?php
/**
 * Handles pages with static content.
 *
 * @author gRegor Morrill, https://gregorlove.com
 * @copyright 2018 gRegor Morrill
 * @license https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

class PageController extends Controller
{
    /**
     * Route that handles the homepage
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        return $this->view->render($response, 'pages/home.twig');
    }

    /**
     * Route that handles the about page
     */
    public function about(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        return $this->view->render($response, 'pages/about.twig');
    }

    /**
     * Route that handles the documentation page
     */
    public function documentation(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        return $this->view->render($response, 'pages/documentation.twig');
    }

    /**
     * Route that handles the updates page
     */
    public function updates(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        return $this->view->render($response, 'pages/updates.twig');
    }
}

