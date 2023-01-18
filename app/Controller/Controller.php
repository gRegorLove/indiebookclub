<?php
/**
 * Controller classes extend this abstract class.
 *
 * @author gRegor Morrill, https://gregorlove.com
 * @copyright 2018 gRegor Morrill
 * @license https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace App\Controller;

use Psr\Container\ContainerInterface;

abstract class Controller
{
    /**
     * @var ContainerInterface $ci
     */
    protected $ci;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->ci = $container;
    }

    /**
     * @param $name
     */
    public function __get($name)
    {
        if ($this->ci->has($name)) {
            return $this->ci->get($name);
        }
    }
}

