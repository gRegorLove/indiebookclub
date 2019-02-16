<?php
namespace App\Controller;

/**
 * Handles user-specific functionality.
 *
 * @author gRegor Morrill, https://gregorlove.com
 * @copyright 2018 gRegor Morrill
 * @license https://opensource.org/licenses/MIT MIT
 */

use \DateTime;
use \ORM;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;

class UsersController extends Controller
{
    /**
     * Route that handles the profile stream
     */
    public function profile(Request $request, Response $response, array $args) {
        $user = $this->get_user_by_slug($args['domain']);

        if (!$user) {
            return $response->withStatus(404);
        }

        $params = $request->getQueryParams();
        $per_page = 10;

        $entries = ORM::for_table('entries')
            ->where('user_id', $user->id);

        if (array_key_exists('before', $params)) {
            $entries->where_lte('id', $params['before']);
        }

        $entries = $entries->limit($per_page)
            ->order_by_desc('published')
            ->find_many();

        $older = $newer = false;

        if (count($entries) > 1) {
            $older = ORM::for_table('entries')
                ->where('user_id', $user->id)
                ->where_lt('id', $entries[count($entries)-1]->id)
                ->order_by_desc('published')
                ->find_one();
        }

        // Check for 'newer' entry id.
        if (array_key_exists('before', $params)) {
            $newer = ORM::for_table('entries')
                ->where('user_id', $user->id)
                ->where_gte('id', $entries[0]->id)
                ->order_by_asc('published')
                ->offset($per_page)
                ->find_one();

            if (!$newer) {
                // No new entry was found at the specific offset, so find the newest post to link to instead
                $newer = ORM::for_table('entries')
                    ->where('user_id', $user->id)
                    ->order_by_desc('published')
                    ->limit(1)
                    ->find_one();

                if ($newer && $newer->id == $entries[0]->id) {
                    $newer = false;
                }
            }
        }

        $extra_headers = [
            sprintf('<link rel="me" href="%s">', $user->url),
        ];
        $this->theme->setData('extra_headers', $extra_headers);

        $feed_name = 'Entries by ';
        $feed_name .= ($user->name) ? htmlspecialchars($user->name) : htmlspecialchars($user->url);

        $this->setTitle($feed_name);
        return $this->theme->render(
            $response,
            'entries',
            [
                'feed_name' => $feed_name,
                'entries' => $entries,
                'user' => $user,
                'older' => ($older ? $older->id : false),
                'newer' => ($newer ? $newer->id : false)
            ]
        );
    }

    /**
     * Route that handles an individual entry
     */
    public function entry(Request $request, Response $response, array $args) {
        $user = $this->get_user_by_slug($args['domain']);

        if (!$user) {
            return $response->withStatus(404);
        }

        $file_path = sprintf('%s/cache/%s-%d.html',
            APP_DIR,
            $user->profile_slug,
            $args['entry']
        );

        if (file_exists($file_path)) {
            return $this->theme->render(
                $response,
                'entry',
                ['cached_entry' => file_get_contents($file_path)]
            );
        }

        $entry = ORM::for_table('entries')
            ->where('user_id', $user->id)
            ->where('id', $args['entry'])
            ->find_one();

        if (!$entry) {
            return $response->withStatus(404);
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
                'user' => $user
            ]
        );
    }

    /**
     * Route that handles the settings page
     */
    public function settings(Request $request, Response $response, array $args) {
        $user = $this->get_user();

        $this->setTitle('Settings');
        return $this->theme->render(
            $response,
            'settings',
            [
                'user' => $user,
                'version' => $this->settings['version'],
            ]
        );
    }

    /**
     * Route that handles the export page
     */
    public function export(Request $request, Response $response, array $args) {
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
}

