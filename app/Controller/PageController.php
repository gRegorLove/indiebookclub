<?php
/**
 * Handles pages with static content.
 *
 * @author gRegor Morrill, https://gregorlove.com
 * @copyright 2018 gRegor Morrill
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PageController extends Controller
{
    /**
     * Route that handles the homepage
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        return $this->theme->render($response, 'index');
    }

    /**
     * Route that handles the about page
     */
    public function about(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $this->setTitle('About');
        return $this->theme->render($response, 'about');
    }

    /**
     * Route that handles the documentation page
     */
    public function documentation(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $this->setTitle('Documentation');
        return $this->theme->render($response, 'documentation');
    }
}

