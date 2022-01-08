<?php
/**
 * Handles user-specific functionality.
 *
 * @author gRegor Morrill, https://gregorlove.com
 * @copyright 2018 gRegor Morrill
 * @license https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace App\Controller;

use DateTime;
use ORM;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UsersController extends Controller
{
    /**
     * Route that handles the profile stream
     */
    public function profile(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $profile = $this->get_user_by_slug($args['domain']);

        if (!$profile) {
            return $response->withStatus(404);
        }

        $params = $request->getQueryParams();
        $per_page = 10;

        $entries = ORM::for_table('entries')
            ->where('user_id', $profile->id)
            ->where_not_equal('visibility', 'unlisted');

        if (array_key_exists('before', $params)) {
            $entries->where_lte('id', $params['before']);
        }

        $entries = $entries->limit($per_page)
            ->order_by_desc('published')
            ->find_many();

        $older = $newer = false;

        if (count($entries) > 1) {
            $older = ORM::for_table('entries')
                ->where('user_id', $profile->id)
                ->where_lt('id', $entries[count($entries)-1]->id)
                ->order_by_desc('published')
                ->find_one();
        }

        // Check for 'newer' entry id.
        if (array_key_exists('before', $params)) {
            $newer = ORM::for_table('entries')
                ->where('user_id', $profile->id)
                ->where_gte('id', $entries[0]->id)
                ->order_by_asc('published')
                ->offset($per_page)
                ->find_one();

            if (!$newer) {
                // No new entry was found at the specific offset, so find the newest post to link to instead
                $newer = ORM::for_table('entries')
                    ->where('user_id', $profile->id)
                    ->order_by_desc('published')
                    ->limit(1)
                    ->find_one();

                if ($newer && $newer->id == $entries[0]->id) {
                    $newer = false;
                }
            }
        }

        $extra_headers = [
            sprintf('<link rel="me" href="%s">', $profile->url),
        ];
        $this->theme->setData('extra_headers', $extra_headers);

        $feed_name = 'Entries by ';
        $feed_name .= ($profile->name) ? htmlspecialchars($profile->name) : htmlspecialchars($profile->url);

        $this->setTitle($feed_name);
        return $this->theme->render(
            $response,
            'entries',
            [
                'feed_name' => $feed_name,
                'entries' => $entries,
                'profile' => $profile,
                'older' => ($older ? $older->id : false),
                'newer' => ($newer ? $newer->id : false)
            ]
        );
    }

    /**
     * Route that handles an individual entry
     */
    public function entry(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $profile = $this->get_user_by_slug($args['domain']);

        if (!$profile) {
            return $response->withStatus(404);
        }

        $entry = ORM::for_table('entries')
            ->where('user_id', $profile->id)
            ->where('id', $args['entry'])
            ->find_one();

        if (!$entry) {
            return $response->withStatus(404);
        }

        if ($entry->visibility == 'private' && $this->utils->session('user_id') !== $entry->user_id) {
            return $response->withStatus(404);
        }

        $file_path = sprintf('%s/cache/%s-%d.html',
            APP_DIR,
            $profile->profile_slug,
            $args['entry']
        );

        if (false && file_exists($file_path)) {
            return $this->theme->render(
                $response,
                'entry',
                ['cached_entry' => file_get_contents($file_path)]
            );
        }

        if ($entry->canonical_url) {
            $extra_headers = [
                sprintf('<link rel="canonical" href="%s">', $entry->canonical_url),
            ];

            $this->theme->setData('extra_headers', $extra_headers);
        }

        return $this->theme->render(
            $response,
            'entry',
            [
                'entry' => $entry,
                'profile' => $profile
            ]
        );
    }

    /**
     * Route that handles the settings page
     */
    public function settings(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $user = $this->get_user();
        $options_visibility = $this->utils->get_visibility_options($user);

        $this->setTitle('Settings');
        return $this->theme->render(
            $response,
            'settings',
            [
                'user' => $user,
                'options_visibility' => $options_visibility,
                'version' => $this->settings['version'],
            ]
        );
    }

    /**
     * Route that handles the settings/update POST request
     */
    public function settings_update(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $data = $request->getParsedBody();

        if (!$this->validate_post_request($data)) {
            $response = $response->withStatus(400);
            return $this->theme->render($response, '400');
        }

        $errors = $this->validate_settings($data);

        if (count($errors) === 0) {
            $this->update_settings($data);
            return $response->withRedirect($this->router->pathFor('settings', [], ['updated' => 1]), 302);
        }
        echo '<pre>', print_r($errors); exit;

        return $this->theme->render(
            $response,
            'settings',
            compact('errors')
        );
    }

    /**
     * Route that handles the export page
     */
    public function export(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $user = $this->get_user();

        if (!$user) {
            $response = $response->withStatus(500);
            return $this->theme->render($response, '500');
        }

        $is_cached = false;

        $latest_entry = ORM::for_table('entries')
            ->where('user_id', $user->id)
            ->order_by_desc('published')
            ->limit(1)
            ->find_one();

        $file_path = sprintf('%s/cache/%s-all.html',
            APP_DIR,
            $user->profile_slug
        );

        if (file_exists($file_path)) {
            $latest = new DateTime($latest_entry->published);
            $cached = new DateTime('@' . filemtime($file_path));

            if ($cached > $latest) {
                $is_cached = true;
            } else {
                unlink($file_path);
            }
        }

        if ($is_cached) {
            $src = file_get_contents($file_path);
        } else {
            $entries = ORM::for_table('entries')
                ->where('user_id', $user->id)
                ->order_by_desc('published')
                ->find_many();

            $feed_name = 'Entries by ';
            $feed_name .= ($user->name) ? htmlspecialchars($user->name) : htmlspecialchars($user->url);

            $src = trim($this->theme->renderView(
                'export',
                [
                    'feed_name' => $feed_name,
                    'entries' => $entries,
                    'user' => $user,
                    'older' => false,
                    'newer' => false,
                ]
            ));

            if (file_put_contents($file_path, $src) === false) {
                $this->logger->error(
                    'Error caching profile.',
                    compact('args')
                );

                $response = $response->withStatus(500);
                return $this->theme->render($response, '500');
            }
        }

        $date = new DateTime();
        $header_disposition = sprintf('Content-Disposition: attachment; filename="indiebookclub-%s-%s.html"',
            $user->profile_slug,
            $date->format('Y-m-d-Hi')
        );
        $header_length = sprintf('Content-Length: %s', mb_strlen($src));

        header('Content-Type: text/html; charset=utf8');
        header($header_disposition);
        header('Cache-Control: max-age=0');
        header('Expires: 0');
        header('Accept-Ranges: bytes');
        header($header_length);
        header('Connection: close');
        echo $src;
        exit;
    }

    /**
     * Validate the POST request
     * @param array $data
     * @return bool
     */
    protected function validate_post_request($data)
    {
        $allowlist = array_fill_keys([
            'default_visibility',
        ], 0);

        if (count(array_diff_key($data, $allowlist)) > 0) {
            return false;
        }

        return true;
    }

    /**
     * Validate settings fields
     * @param array $data
     * @return array
     */
    protected function validate_settings($data)
    {
        $errors = [];

        if (array_key_exists('default_visibility', $data)) {
            if (!in_array($data['default_visibility'], ['public', 'private', 'unlisted'])) {
                $errors[] = 'Invalid selection for <i>Default Visibility</i>';
            }
        }

        return $errors;
    }

    /**
     * Update settings table
     * @param int $id
     * @return bool
     */
    protected function update_settings($data)
    {
        try {
            $user = $this->get_user();

            if (!$user) {
                throw new Exception('Could not load user');
            }

            if (array_key_exists('default_visibility', $data)) {
                $user->default_visibility = $data['default_visibility'];
            }

            $user->save();
            return true;
        } catch (PDOException $e) {
            $this->logger->error(
                'Error updating settings. ' . $e->getMessage(),
                $data
            );
            return false;
        }
    }
}

