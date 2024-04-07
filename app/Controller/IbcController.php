<?php
/**
 * Handles core indiebookclub functions.
 *
 * Portions of this file were originally written by Aaron Parecki.
 * gRegor Morrill modified for indiebookclub and Slim Framework 3.
 *
 * MIT license except where noted otherwise.
 *
 * @author gRegor Morrill, https://gregorlove.com
 * @copyright 2018 gRegor Morrill
 * @license https://opensource.org/licenses/MIT MIT
 * @see https://github.com/aaronpk/Teacup
 */

declare(strict_types=1);

namespace App\Controller;

use DateTime;
use Exception;
use PDOException;
use Mwhite\PhpIsbn\Isbn;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

class IbcController extends Controller
{
    /**
     * Handle the new post process
     */
    public function new(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ) {
        $user = $this->User->get($this->utils->session('user_id'));
        $validation_errors = [];

        if ($request->isPost()) {
            $data = $request->getParsedBody();

            if (!$this->validate_post_request($data)) {
                $response = $response->withStatus(400);
                return $this->view->render($response, 'pages/400.twig');
            }

            $validation_errors = $this->validate_new_post($data);

            if (count($validation_errors) === 0) {

                if ($user['micropub_endpoint']) {
                    $mp_request = $this->build_micropub_request($data);

                    $mp_response = $this->utils->micropub_post(
                        $user['micropub_endpoint'],
                        $mp_request,
                        $this->utils->getAccessToken(),
                        true
                    );

                    $response_body = trim($mp_response['response']);

                    $canonical_url = null;
                    if (isset($mp_response['headers']['Location'])) {
                        $canonical_url = $data['canonical_url'] = $mp_response['headers']['Location'][0];
                        $data['micropub_success'] = 1;
                    }

                    # DEBUG
                    // $response_body = 'debug';
                    // $canonical_url = $data['canonical_url'] = 'https://example.com/debug';
                    // $data['micropub_success'] = 1;
                    // $data['post_status'] = 'published';

                    # Update user
                    $user = $this->User->update(
                        $this->utils->session('user_id'),
                        [
                            'last_micropub_response' => $response_body,
                        ]
                    );

                    if ('draft' == $data['post_status']) {
                        # drafts are only sent to Micropub endpoint, not stored on IBC

                        # default redirect: IBC profile
                        $url = $this->router->pathFor('profile', ['domain' => $user['profile_slug']]);
                        if ($canonical_url) {
                            # redirect to canonical
                            $url = $canonical_url;
                        }

                        return $response->withRedirect($url, 302);
                    }

                    # continue to add entry to IBC at this point

                    $data['micropub_response'] = $response_body;
                }

                $data['user_id'] = $this->utils->session('user_id');

                if ($data['isbn'] = Isbn::to13($data['isbn'])) {
                    $this->Book->addOrIncrement($data);
                }

                $data['category'] = $this->utils->normalizeSeparatedString($data['category']);
                $entry = $this->Entry->add($data);

                if (!$entry) {
                    $response = $response->withStatus(500);
                    return $this->view->render($response, 'pages/500.twig');
                }

                $entry_id = (int) $entry['id'];
                $this->cache_entry($entry_id);

                # default redirect: IBC permalink
                $url = $this->router->pathFor(
                    'entry',
                    [
                        'domain' => $user['profile_slug'],
                        'entry' => $entry_id,
                    ]
                );

                if ($entry['canonical_url']) {
                    # redirect to canonical
                    $url = $entry['canonical_url'];
                }

                return $response->withRedirect($url, 302);
            }
        }

        $options_status = $this->utils->get_read_status_options();
        $options_post_status = $this->utils->get_post_status_options();
        $options_visibility = $this->utils->get_visibility_options($user);

        $post_status = null;
        if ($this->utils->hasScope($user['token_scope'], 'draft')) {
            $post_status = 'draft';
        }

        if ($temp_status = $request->getQueryParam('post-status')) {
            $post_status = strtolower($temp_status);
            if (!in_array($post_status, $this->utils->get_post_status_options())) {
                $post_status = 'published';
            }
        }

        if ($read_status = $request->getQueryParam('read-status')) {
            $read_status = strtolower($read_status);
        }

        if (!in_array($read_status, ['to-read', 'reading', 'finished'])) {
            $read_status = 'to-read';
        }

        $read_title = $this->utils->sanitize($request->getQueryParam('title'));
        $read_authors = $this->utils->sanitize($request->getQueryParam('authors'));
        $read_isbn = $this->utils->sanitize($request->getQueryParam('isbn'));
        $read_doi = $this->utils->sanitize($request->getQueryParam('doi'));
        $read_tags = $this->utils->sanitize($request->getQueryParam('tags'));

        if ($read_of = $request->getQueryParam('read-of')) {
            $parsed = $this->utils->parse_read_of($read_of);
            $read_title = $this->utils->sanitize($parsed['title']);
            $read_authors = $this->utils->sanitize($parsed['authors']);
            $read_isbn = $this->utils->sanitize($parsed['uid']);
        }

        return $this->view->render(
            $response,
            'pages/new-post.twig',
            compact(
                'user',
                'read_status',
                'read_title',
                'read_authors',
                'read_isbn',
                'read_doi',
                'read_tags',
                'post_status',
                'options_status',
                'options_post_status',
                'options_visibility',
                'validation_errors'
            )
        );
    }

    /**
     * Re-try a Micropub request
     *
     * Ensures the post is owned by the current user and has not been
     * published to their site already before sending the Micropub
     * request.
     * @see https://github.com/gRegorLove/indiebookclub/issues/13
     */
    public function retry(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ) {
        $user = $this->User->get($this->utils->session('user_id'));

        $entry_id = (int) $args['entry_id'];
        $entry = $this->Entry->getUserEntry($entry_id, $this->utils->session('user_id'));
        if (!$entry) {
            return $response->withStatus(404);
        }

        if ($entry['canonical_url']) {
            return $this->view->render($response, 'pages/400.twig', [
                'short_title' => 'Error',
                'message' => sprintf('<p> This post has already been published on your site. <a href="%s" target="_blank" rel="noopener">View the post</a>. </p>',
                    $entry['canonical_url']
                ),
            ]);
        }

        if (!$user['micropub_endpoint']) {
            $response = $response->withStatus(400);
            return $this->view->render($response, 'pages/400.twig', [
                'short_title' => 'Micropub Error',
                'message' => '<p> Your site does not appear to support Micropub. </p>',
            ]);
        }

        $url = $this->router->pathFor(
            'entry',
            [
                'domain' => $user['profile_slug'],
                'entry' => $entry_id,
            ]
        );

        // Send to the micropub endpoint and store the result.
        $mp_request = $this->build_micropub_request($entry);

        $mp_response = $this->utils->micropub_post(
            $user['micropub_endpoint'],
            $mp_request,
            $this->utils->getAccessToken(),
            true
        );

        $response_body = trim($mp_response['response']);

        # Update user
        $user = $this->User->update($this->utils->session('user_id'), [
            'last_micropub_response' => $response_body,
        ]);

        # Update entry
        $entry_data = [
            'micropub_response' => $response_body,
        ];

        if (isset($mp_response['headers']['Location'])) {
            $entry_data['canonical_url'] = reset($mp_response['headers']['Location']);
            $entry_data['micropub_success'] = 1;
        }

        $entry = $this->Entry->update($entry_id, $entry_data);

        if ($entry['canonical_url']) {
            $url = $entry['canonical_url'];
        }

        return $response->withRedirect($url, 302);
    }

    public function delete(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ) {
        $profile = $this->User->get($this->utils->session('user_id'));
        $errors = [];

        if ($request->isPost()) {
            $data = $request->getParsedBody();

            $allowlist = array_fill_keys([
                'confirm_delete',
                'mp_delete',
                'id',
            ], 0);

            if (!$this->validate_post_request($data, $allowlist)) {
                $response = $response->withStatus(400);
                return $this->view->render($response, 'pages/400.twig');
            }

            $errors = $this->validate_delete_post($data);

            if (count($errors) === 0) {
                # double check: can only delete own posts
                $entry_id = (int) $data['id'];
                $entry = $this->Entry->getUserEntry($entry_id, $this->utils->session('user_id'));
                if (!$entry) {
                    $response = $response->withStatus(403);
                    return $this->view->render($response, 'pages/400.twig');
                }

                // Send delete to the micropub endpoint
                if ($profile['micropub_endpoint'] && $data['mp_delete'] == 'yes' && $entry['canonical_url']) {
                    $mp_request = [
                        'action' => 'delete',
                        'url' => $entry['canonical_url'],
                    ];

                    $mp_response = $this->utils->micropub_post(
                        $profile['micropub_endpoint'],
                        $mp_request,
                        $this->utils->getAccessToken()
                    );

                    $response_body = trim($mp_response['response']);

                    # Update user
                    $user = $this->User->update($this->utils->session('user_id'), [
                        'last_micropub_response' => $response_body,
                    ]);
                }

                $this->uncache_entry($entry_id);
                $this->Entry->delete($entry_id);

                $url = $this->router->pathFor('profile', ['domain' => $profile['profile_slug']]);
                return $response->withRedirect($url, 302);
            }

        } elseif ($request->isGet()) {
            $entry = $this->Entry->getUserEntry((int) $args['id'], $this->utils->session('user_id'));
            if (!$entry) {
                return $response->withStatus(404);
            }
        }

        $is_micropub_post = ($entry['micropub_success'] && $entry['canonical_url']);
        $has_micropub_delete = $this->utils->hasScope($profile['token_scope'], 'delete');

        $is_caching = true;
        return $this->view->render(
            $response,
            'pages/delete.twig',
            compact(
                'errors',
                'entry',
                'profile',
                'is_micropub_post',
                'has_micropub_delete',
                'is_caching'
            )
        );
    }

    /**
     * Route that handles the ISBN stream
     */
    public function isbn(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ) {
        $limit = 2; # default is 10
        $isbn = $args['isbn'];
        $before = (int) $request->getQueryParam('before');
        $entries = $this->Entry->findByIsbn($isbn, $before, $limit);

        $last_id = (int) end($entries)['id'];
        $first_id = (int) reset($entries)['id'];

        $older_id = $this->Entry->getOlderByIsbn($isbn, $last_id);
        $newer_id = $this->Entry->getNewerByIsbn($isbn, $first_id);

        return $this->view->render(
            $response,
            'pages/isbn.twig',
            compact('isbn', 'entries', 'before', 'older_id', 'newer_id')
        );
    }

    public function review(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ) {
        $year = (int) $args['year'];
        if ($year < 2023) {
            return $response->withStatus(404);
        }

        $dt = new DateTime();
        $dt_available = new DateTime(sprintf('%d-11-30', $year));

        if ($dt < $dt_available) {
            $messages = [
                'Flux capacitor required to view this page.',
                'Isn’t time flying by fast enough already?',
                'Patience is a virtue.',
            ];
            $index = array_rand($messages, 1);

            $response = $response->withStatus(404);
            return $this->view->render(
                $response,
                'pages/400.twig',
                [
                    'short_title' => 'Not Yet!',
                    'message' => sprintf('<p> %s </p>', $messages[$index])
                ]
            );
        }

        $file_path = sprintf('%s/cache/%d-review.html',
            APP_DIR,
            $year
        );

        # after the new year, don't need to refresh the cache
        $is_final = ($dt->format('Y') > $year);
        $is_cached = ($is_final && file_exists($file_path));
        if ($is_cached) {
            echo file_get_contents($file_path);
            exit;
        }

        # before new year, check if cache is more than 1 day old
        if (file_exists($file_path)) {
            $cache_time = filemtime($file_path);
            if (false !== $cache_time) {
                $dt_cache = new DateTime('@' . $cache_time);
                $interval = $dt->diff($dt_cache);
                $days = (int) $interval->format('%a');
                if ($days < 1) {
                    $is_cached = true;
                }
            }
        }

        if ($is_cached) {
            echo file_get_contents($file_path);
            exit;
        }

        # refresh the cache
        $start_date = sprintf('%d-01-01', $year);
        $end_date = sprintf('%d-12-31', $year);

        $number_new_entries = $this->Entry->getNewCount(
            $start_date,
            $end_date
        );

        $number_new_books = $this->Book->getNewCount(
            $start_date,
            $end_date
        );

        $distinct_entries = $this->Entry->findDistinct(
            $start_date,
            $end_date
        );

        $number_new_users = $this->User->getNewCount(
            $start_date,
            $end_date
        );

        $number_logins = $this->User->getLoginCount(
            $start_date,
            $end_date
        );

        $html = $this->view->fetch(
            'pages/review.twig',
            compact(
                'dt',
                'year',
                'is_final',
                'number_new_entries',
                'number_new_books',
                'distinct_entries',
                'number_new_users',
                'number_logins',
            )
        );

        if (file_put_contents($file_path, trim($html)) === false) {
            throw new Exception('Could not write review cache file');
        }

        echo $html;
        exit;
    }

    protected function validate_post_request(array $data, array $allowlist = []): bool
    {
        if (!$allowlist) {
            $allowlist = array_fill_keys([
                'read_status',
                'title',
                'authors',
                'switch-uid',
                'doi',
                'isbn',
                'category',
                'post_status',
                'visibility',
                'published',
                'tz_offset',
            ], 0);
        }

        if (count(array_diff_key($data, $allowlist)) > 0) {
            return false;
        }

        return true;
    }

    /**
     * Validate new post fields
     *
     * Returns an array of error messages
     */
    protected function validate_new_post(array $data): array
    {
        $errors = [];

        if (!$data['read_status']) {
            $errors[] = 'Please select the <i>Read Status</i>';
        }

        if (!$data['title']) {
            $errors[] = 'Please enter the <i>Title</i>';
        }

        if ($data['isbn'] && Isbn::to13($data['isbn'], true) === false) {
            $errors[] = 'The <i>ISBN</i> entered appears to be invalid';
        }

        if ($data['published']) {
            try {
                $dt = new DateTime($data['published']);
                $temp_errors = DateTime::getLastErrors();

                if (!empty($temp_errors['warning_count'])) {
                    throw new Exception();
                }
            } catch (Exception $e) {
                $errors[] = 'The <i>Published</i> datetime appears to be invalid';
            }
        }

        return $errors;
    }

    /**
     * Validate delete post fields
     *
     * Returns an array of error messages
     */
    protected function validate_delete_post(array $data): array
    {
        $errors = [];

        if ($data['confirm_delete'] == 'no') {
            $errors[] = 'Please check the box to confirm deletion';
        }

        return $errors;
    }

    /**
     * Cache read post
     */
    protected function cache_entry(int $id): bool
    {
        try {
            $entry = $this->Entry->get($id);
            if (!$entry) {
                throw new Exception('Could not load entry');
            }

            $profile = $this->User->get((int) $entry['user_id']);
            if (!$profile) {
                throw new Exception('Could not load user');
            }

            $is_caching = true;
            $src = $this->view->fetch(
                'partials/entry.twig',
                compact('entry', 'profile', 'is_caching')
            );

            $file_path = sprintf('%s/cache/%s-%d.html',
                APP_DIR,
                $profile['profile_slug'],
                $id
            );

            if (file_put_contents($file_path, trim($src)) === false) {
                throw new Exception('Could not write file');
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error(
                'Error caching entry: ' . $e->getMessage(),
                compact('id')
            );
            return false;
        }
    }

    /**
     * Un-cache read post
     */
    protected function uncache_entry(int $id): bool
    {
        try {
            $entry = $this->Entry->get($id);
            if (!$entry) {
                throw new Exception('Could not load entry');
            }

            $user = $this->User->get((int) $entry['user_id']);
            if (!$user) {
                throw new Exception('Could not load user');
            }

            $file_path = sprintf('%s/cache/%s-%d.html',
                APP_DIR,
                $user['profile_slug'],
                $id
            );

            if (file_exists($file_path)) {
                unlink($file_path);
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error(
                'Error un-caching entry: ' . $e->getMessage(),
                compact('id')
            );
            return false;
        }
    }

    /**
     * Build Micropub request from the entry
     */
    protected function build_micropub_request(array $data): array
    {
        $summary = sprintf('%s: %s',
            $this->utils->get_read_status_for_humans($data['read_status']),
            $data['title']
        );

        $cite = [
            'type' => ['h-cite'],
            'properties' => [
                'name' => [$data['title']],
            ]
        ];

        if ($data['authors']) {
            $cite['properties']['author'] = [$data['authors']];
            $summary .= sprintf(' by %s', $data['authors']);
        }

        if ($doi = $data['doi']) {
            if (stripos($doi, 'doi:') !== 0) {
                $doi = 'doi:' . $doi;
            }

            $cite['properties']['uid'] = [$doi];
            $summary .= sprintf(', %s', $doi);
        } elseif ($data['isbn']) {
            $cite['properties']['uid'] = ['isbn:' . $data['isbn']];
            $summary .= sprintf(', ISBN: %s', $data['isbn']);
        }

        $properties = [
            'summary' => [$summary],
            'read-status' => [$data['read_status']],
            'read-of' => [$cite],
            'post-status' => [$data['post_status']],
            'visibility' => [$data['visibility']],
        ];

        if (array_key_exists('published', $data) && $data['published']) {
            $properties['published'] = [
                $this->Entry->get_datetime_with_offset(
                    $data['published'],
                    (int) $data['tz_offset']
                ),
            ];
        }

        if ($data['category']) {
            $properties['category'] = $this->utils->get_category_array($data['category']);
        }

        return [
            'type' => ['h-entry'],
            'properties' => $properties,
        ];
    }
}

