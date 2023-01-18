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
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

class UsersController extends Controller
{
    /**
     * Route that handles the profile stream
     */
    public function profile(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $profile = $this->User->findBySlug($args['domain']);
        if (!$profile) {
            return $response->withStatus(404);
        }

        // $limit = 10; # default is 10
        $user_id = (int) $profile['id'];
        $before = (int) $request->getQueryParam('before');
        $entries = $this->Entry->findByUser($user_id, $before);

        $older_id = $newer_id = null;
        if ($entries) {
            $last_id = (int) end($entries)['id'];
            $first_id = (int) reset($entries)['id'];

            $older_id = $this->Entry->getOlderId($user_id, $last_id);
            $newer_id = $this->Entry->getNewerId($user_id, $first_id);
        }

        return $this->view->render(
            $response,
            'pages/profile.twig',
            compact(
                'profile',
                'entries',
                'before',
                'older_id',
                'newer_id'
            )
        );
    }

    /**
     * Route that handles an individual entry
     */
    public function entry(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $profile = $this->User->findBySlug($args['domain']);
        if (!$profile) {
            return $response->withStatus(404);
        }

        $id = (int) $args['entry'];
        $user_id = (int) $profile['id'];

        $entry = $this->Entry->getUserEntry($id, $user_id);
        if (!$entry) {
            return $response->withStatus(404);
        }

        if ($entry['visibility'] == 'private' && $this->utils->session('user_id') !== $entry['user_id']) {
            return $response->withStatus(404);
        }

        $can_retry = false;
        $is_own_post = ($entry['user_id'] == $this->utils->session('user_id'));
        $micropub_failed = (
            ($profile['type'] == 'micropub')
            && ($entry['micropub_success'] == 0)
            && empty($entry['canonical_url'])
        );

        if ($is_own_post && $micropub_failed) {
            $can_retry = true;
        }

        $cached_entry = null;
        if ($entry['user_id'] != $this->utils->session('user_id')) {
            # not the post author, check for cached entry
            $file_path = sprintf('%s/cache/%s-%d.html',
                APP_DIR,
                $profile['profile_slug'],
                $id
            );

            if (file_exists($file_path)) {
                $cached_entry = '<!-- cache -->' . PHP_EOL . file_get_contents($file_path);
            }
        }

        return $this->view->render(
            $response,
            'pages/entry.twig',
            compact(
                'profile',
                'entry',
                'cached_entry',
                'can_retry'
            )
        );
    }

    /**
     * Route that handles the settings page
     */
    public function settings(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $validation_errors = [];
        $user = $this->User->get($this->utils->session('user_id'));
        $options_visibility = $this->utils->get_visibility_options($user);

        $supported_visibility = $user['supported_visibility'] ?? null;
        if ($supported_visibility) {
            if ($options = json_decode($supported_visibility)) {
                $supported_visibility = implode(', ', $options);
            }
        }

        $access_token = $this->utils->getAccessToken();
        $token_ending = null;
        if ($token_length = strlen($access_token)) {
            $token_ending = substr($access_token, -7);
        }

        $version = $this->settings['version'];

        if ($this->utils->session('validation_errors')) {
            $validation_errors = $this->utils->session('validation_errors');
            unset($_SESSION['validation_errors']);
        }

        return $this->view->render(
            $response,
            'pages/settings.twig',
            compact(
                'validation_errors',
                'user',
                'supported_visibility',
                'options_visibility',
                'token_length',
                'token_ending',
                'version'
            )
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
            return $this->view->render($response, 'pages/400.twig');
        }

        $errors = $this->validate_settings($data);

        if (count($errors) === 0) {
            $user = $this->User->update($this->utils->session('user_id'), $data);
            if (!$user) {
                $response = $response->withStatus(500);
                return $this->view->render($response, 'pages/500.twig');
            }

            $redirect = $this->router->pathFor('settings', [], ['updated' => 1]);
            return $response->withRedirect($redirect, 302);
        }

        $_SESSION['validation_errors'] = $errors;
        return $response->withRedirect($this->router->pathFor('settings'), 302);
    }

    /**
     * Route that handles the export page
     */
    public function export(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $profile = $this->User->get($this->utils->session('user_id'));
        if (!$profile) {
            $response = $response->withStatus(500);
            return $this->view->render($response, 'pages/500.twig');
        }

        $is_cached = false;
        $latest_entry = $this->Entry->getUserLatestEntry($this->utils->session('user_id'));

        $file_path = sprintf('%s/cache/%s-all.html',
            APP_DIR,
            $profile['profile_slug']
        );

        if (file_exists($file_path)) {
            $latest = new DateTime($latest_entry['published']);
            $cached = new DateTime('@' . filemtime($file_path));

            if ($cached > $latest) {
                $is_cached = true;
            } else {
                unlink($file_path);
            }
        }

        $dt = new DateTime();

        if ($is_cached) {
            $src = file_get_contents($file_path);
        } else {
            $is_caching = true;
            $inline_css = trim(file_get_contents(APP_DIR . '/public/css/style.css'));
            $entries = $this->Entry->findByUserExport($this->utils->session('user_id'));
            $export_timestamp = $dt->format('Y-m-d H:i:sO');

            $src = trim($this->view->fetch(
                'pages/export.twig',
                compact(
                    'is_caching',
                    'inline_css',
                    'profile',
                    'entries',
                    'export_timestamp'
                )
            ));

            if (file_put_contents($file_path, $src) === false) {
                $this->logger->error(
                    'Error caching profile.',
                    compact('args')
                );

                $response = $response->withStatus(500);
                return $this->view->render($response, 'pages/500.twig');
            }
        }

        $header_disposition = sprintf('Content-Disposition: attachment; filename="indiebookclub-%s-%s.html"',
            $profile['profile_slug'],
            $dt->format('Y-m-d-Hi')
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

    private function validate_post_request(array $data): bool
    {
        $allowlist = array_fill_keys([
            'default_visibility',
        ], 0);

        if (count(array_diff_key($data, $allowlist)) > 0) {
            return false;
        }

        return true;
    }

    private function validate_settings(array $data): array
    {
        $errors = [];

        if (array_key_exists('default_visibility', $data)) {
            if (!in_array($data['default_visibility'], ['public', 'private', 'unlisted'])) {
                $errors[] = 'Invalid selection for <i>Default Visibility</i>';
            }
        }

        return $errors;
    }
}

