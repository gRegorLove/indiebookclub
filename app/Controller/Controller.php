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

use ORM;
use PDOException;
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

    /**
     * Set a custom <title>
     * @param string $title
     */
    protected function setTitle($title)
    {
        $this->theme->setData('title', $title . ' - indiebookclub');
    }

    /**
     * Get current user
     * @return ORM|bool
     */
    public function get_user()
    {
        try {
            return ORM::for_table('users')
                ->where('id', $_SESSION['user_id'])
                ->find_one();
        } catch (PDOException $e) {
            $this->logger->error(
                'Error getting user. ' . $e->getMessage(),
                ['id' => $_SESSION['user_id']]
            );
            return false;
        }
    }

    /**
     * Get user by id
     * @param int $slug
     * @return ORM|bool
     */
    public function get_user_by_id($id)
    {
        try {
            return ORM::for_table('users')
                ->where('id', $id)
                ->find_one();
        } catch (PDOException $e) {
            $this->logger->error(
                'Error getting user. ' . $e->getMessage(),
                compact('id')
            );
            return false;
        }
    }

    /**
     * Get user by profile slug
     * @param string $slug
     * @return ORM|bool
     */
    public function get_user_by_slug($slug)
    {
        try {
            return ORM::for_table('users')
                ->where('profile_slug', $slug)
                ->find_one();
        } catch (PDOException $e) {
            $this->logger->error(
                'Error getting user. ' . $e->getMessage(),
                compact('slug')
            );
            return false;
        }
    }
}

