<?php
namespace App\Controller;

/**
 * Handles pages with static content.
 *
 * @author gRegor Morrill, https://gregorlove.com
 * @copyright 2018 gRegor Morrill
 * @license https://opensource.org/licenses/MIT MIT
 */

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;

class PageController extends Controller
{
    /**
     * Route that handles the homepage
     */
    public function index(Request $request, Response $response, array $args) {
        return $this->theme->render($response, 'index');
    }

    /**
     * Route that handles the about page
     */
    public function about(Request $request, Response $response, array $args) {
        $this->setTitle('About');
        return $this->theme->render($response, 'about');
    }

    /**
     * Route that handles the documentation page
     */
    public function documentation(Request $request, Response $response, array $args) {
        $this->setTitle('Documentation');
        return $this->theme->render($response, 'documentation');
    }
}

