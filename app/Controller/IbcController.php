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
use ORM;
use PDOException;
use Mwhite\PhpIsbn\Isbn;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IbcController extends Controller
{
    /**
     * Route that handles the new post process
     */
    public function new(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $user = $this->get_user();
        $errors = [];

        if ($request->isPost()) {
            $data = $request->getParsedBody();

            if (!$this->validate_post_request($data)) {
                $response = $response->withStatus(400);
                return $this->theme->render($response, '400');
            }

            $errors = $this->validate_new_post($data);

            if (count($errors) === 0) {
                $data['user_id'] = $user->id;

                if ($data['isbn'] = Isbn::to13($data['isbn'])) {
                    $this->add_book($data['isbn'], $user->id);
                }

                $entry = $this->add_entry($data);

                if ($entry === false) {
                    $response = $response->withStatus(500);
                    return $this->theme->render($response, '500');
                }

                $this->cache_entry((int) $entry->id);

                $url = $this->router->pathFor(
                    'entry',
                    [
                        'domain' => $user->profile_slug,
                        'entry' => $entry->id
                    ]
                );

                // Send to the micropub endpoint (if one is defined) and store the result.
                if ($user->micropub_endpoint) {
                    $mp_request = $this->build_micropub_request($data);

                    $mp_response = $this->utils->micropub_post(
                        $user->micropub_endpoint,
                        $mp_request,
                        $this->utils->getAccessToken(),
                        true
                    );

                    $this->add_micropub_response($mp_response, $user, $entry);

                    if ($entry->canonical_url) {
                        $url = $entry->canonical_url;
                    }
                }

                return $response->withRedirect($url, 302);
            }
        }

        $options_visibility = $this->utils->get_visibility_options($user);

        if ($read_status = $request->getQueryParam('read-status', null)) {
            $read_status = strtolower($read_status);
        }

        if (!in_array($read_status, ['to-read', 'reading', 'finished'])) {
            $read_status = 'to-read';
        }

        $read_title = $this->utils->sanitize($request->getQueryParam('title', null));
        $read_authors = $this->utils->sanitize($request->getQueryParam('authors', null));
        $read_isbn = $this->utils->sanitize($request->getQueryParam('isbn', null));
        $read_doi = $this->utils->sanitize($request->getQueryParam('doi', null));
        $read_tags = $this->utils->sanitize($request->getQueryParam('tags', null));

        if ($read_of = $request->getQueryParam('read-of', null)) {
            $parsed = $this->utils->parse_read_of($read_of);
            $read_title = $this->utils->sanitize($parsed['title']);
            $read_authors = $this->utils->sanitize($parsed['authors']);
            $read_isbn = $this->utils->sanitize($parsed['uid']);
        }

        $this->setTitle('New Post');
        return $this->theme->render(
            $response,
            'new-post',
            [
                'user' => $user,
                'read_status' => $read_status,
                'read_title' => $read_title,
                'read_authors' => $read_authors,
                'read_isbn' => $read_isbn,
                'read_doi' => $read_doi,
                'read_tags' => $read_tags,
                'options_visibility' => $options_visibility,
                'micropub_endpoint' => $user->micropub_endpoint,
                'micropub_media_endpoint' => $user->micropub_media_endpoint,
                'token_scope' => $user->token_scope,
                'response_date' => $user->last_micropub_response_date,
                'tz_offset' => '+0000',
                'errors' => $errors,
            ]
        );
    }

    /**
     * Future functionality
     * @see https://github.com/gRegorLove/indiebookclub/issues/13
     */
    // public function retry(ServerRequestInterface $request, ResponseInterface $response, array $args)
    // {}

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $profile = $this->get_user();
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
                return $this->theme->render($response, '400');
            }

            $errors = $this->validate_delete_post($data);

            if (count($errors) === 0) {
                # double check: can only delete own posts
                $entry = ORM::for_table('entries')
                    ->where('user_id', $this->utils->session('user_id'))
                    ->where('id', $data['id'])
                    ->find_one();

                if (!$entry) {
                    return $response->withStatus(403);
                }

                // Send delete to the micropub endpoint
                if ($profile->micropub_endpoint && $data['mp_delete'] == 'yes' && $entry->canonical_url) {
                    $mp_request = [
                        'action' => 'delete',
                        'url' => $entry->canonical_url,
                    ];

                    $mp_response = $this->utils->micropub_post(
                        $profile->micropub_endpoint,
                        $mp_request,
                        $this->utils->getAccessToken()
                    );

                    $this->add_last_micropub_response($mp_response, $profile);
                }

                $this->uncache_entry((int) $data['id']);
                $entry->delete();

                $url = $this->router->pathFor('profile', ['domain' => $profile->profile_slug]);
                return $response->withRedirect($url, 302);
            }

        } elseif ($request->isGet()) {
            $entry = ORM::for_table('entries')
                ->where('user_id', $this->utils->session('user_id'))
                ->where('id', $args['id'])
                ->find_one();

            if (!$entry) {
                return $response->withStatus(404);
            }
        }

        $is_micropub_post = ($entry->micropub_success && $entry->canonical_url);
        $has_micropub_delete = $this->utils->hasMicropubDelete($profile->token_scope);

        return $this->theme->render(
            $response,
            'delete',
            compact('errors', 'entry', 'profile', 'is_micropub_post', 'has_micropub_delete')
        );
    }

    /**
     * Route that handles the ISBN stream
     */
    public function isbn(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $params = $request->getQueryParams();
        $per_page = 10;

        $entries = ORM::for_table('entries')
            ->table_alias('e')
            ->select_many(
                'e.*',
                ['user_url' => 'u.url'],
                ['user_profile_slug' => 'u.profile_slug'],
                ['user_photo_url' => 'u.photo_url'],
                ['user_name' => 'u.name']
            )
            ->join('users', ['e.user_id', '=', 'u.id'], 'u')
            ->where('e.isbn', $args['isbn'])
            ->where('visibility', 'public')
            ->order_by_desc('e.published')
            ->limit($per_page);

        if (array_key_exists('before', $params)) {
            $entries->where_lte('id', $params['before']);
        }

        $entries = $entries->find_many();

        if (!$entries) {
            return $response->withStatus(404);
        }

        $older = $newer = false;

        // Check for 'older' entry id.
        if (count($entries) > 1) {
            $older = ORM::for_table('entries')
                ->where('isbn', $args['isbn'])
                ->where_lt('id', $entries[count($entries)-1]->id)
                ->order_by_desc('published')
                ->find_one();
        }

        // Check for 'newer' entry id.
        if (array_key_exists('before', $params)) {
            $newer = ORM::for_table('entries')
                ->where('isbn', $args['isbn'])
                ->where_gte('id', $entries[0]->id)
                ->order_by_asc('published')
                ->offset($per_page)
                ->find_one();

            if (!$newer) {
                // no new entry was found at the specific offset, so find the newest post to link to instead
                $newer = ORM::for_table('entries')
                    ->where('isbn', $args['isbn'])
                    ->order_by_desc('published')
                    ->limit(1)
                    ->find_one();

                if ($newer && $newer->id == $entries[0]->id) {
                    $newer = false;
                }
            }

        }

        return $this->theme->render(
            $response,
            'isbn',
            [
                'isbn' => $args['isbn'],
                'entries' => $entries,
                'older' => ($older ? $older->id : false),
                'newer' => ($newer ? $newer->id : false),
            ]
        );
    }

    /**
     * Validate the POST request
     * @param array $data
     * @return bool
     */
    protected function validate_post_request($data, $allowlist = [])
    {
        if (!$allowlist) {
            $allowlist = array_fill_keys([
                'read_status',
                'title',
                'authors',
                'switch-uid',
                'doi',
                'isbn',
                'tags',
                'visibility',
                'tzoffset',
            ], 0);
        }

        if (count(array_diff_key($data, $allowlist)) > 0) {
            return false;
        }

        return true;
    }

    /**
     * Validate new post fields
     * @param array $data
     * @return array
     */
    protected function validate_new_post($data)
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

        return $errors;
    }

    /**
     * Validate delete post fields
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
     * Add/increment ISBN book record to database
     * @param string $isbn
     * @param int $user_id
     * @return bool
     */
    protected function add_book($isbn, $user_id)
    {
        try {
            $book = ORM::for_table('books')
                ->where('isbn', $isbn)
                ->find_one();

            if ($book) {
                $book->entry_count += 1;
            } else {
                $book = ORM::for_table('books')->create();
                $book->isbn = $isbn;
                $book->entry_count = 1;
                $book->first_user_id = $user_id;
                $book->set_expr('created', 'NOW()');
                $book->set_expr('modified', 'NOW()');
            }

            $book->save();
            return true;
        } catch (PDOException $e) {
            $this->logger->error(
                'Error adding book. ' . $e->getMessage(),
                compact('isbn', 'user_id')
            );
            return false;
        }
    }

    /**
     * Add read post to database
     * @param array $data
     * @return ORM|bool
     */
    protected function add_entry($data)
    {
        try {
            $entry = ORM::for_table('entries')->create();
            $published = new DateTime();
            $entry->isbn = ($data['isbn']) ? $data['isbn'] : '';
            $entry->doi = $data['doi'];
            $entry->user_id = $data['user_id'];
            $entry->published = $published->format('Y-m-d H:i:s');
            $entry->tz_offset = $this->utils->tz_offset_to_seconds($data['tzoffset']);
            $entry->read_status = $data['read_status'];
            $entry->title = $data['title'];
            $entry->authors = $data['authors'];
            $entry->category = $this->utils->normalizeSeparatedString($data['tags']);
            $entry->visibility = $data['visibility'];
            $entry->save();
            return $entry;
        } catch (PDOException $e) {
            $this->logger->error(
                'Error adding entry. ' . $e->getMessage(),
                $data
            );
            return false;
        }
    }

    /**
     * Cache read post
     * @param int $id
     * @return bool
     */
    protected function cache_entry(int $id): bool
    {
        $entry = ORM::for_table('entries')
            ->where('id', $id)
            ->find_one();

        try {
            if (!$entry) {
                throw new Exception('Could not load entry');
            }

            $profile = $this->get_user_by_id($entry->user_id);
            if (!$profile) {
                throw new Exception('Could not load user');
            }

            $is_caching = true;
            $src = $this->theme->renderView(
                'partials/entry',
                compact('entry', 'profile', 'is_caching')
            );

            $file_path = sprintf('%s/cache/%s-%d.html',
                APP_DIR,
                $profile->profile_slug,
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
     * @param int $id
     * @return bool
     */
    protected function uncache_entry(int $id): bool
    {
        $entry = ORM::for_table('entries')
            ->where('id', $id)
            ->find_one();

        try {
            if (!$entry) {
                throw new Exception('Could not load entry');
            }

            $user = $this->get_user_by_id($entry->user_id);

            if (!$user) {
                throw new Exception('Could not load user');
            }

            $file_path = sprintf('%s/cache/%s-%d.html',
                APP_DIR,
                $user->profile_slug,
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
     * Update database with Micropub response
     * @param array $mp_response
     * @param ORM &$user
     * @param ORM &$entry
     * @return bool
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    protected function add_micropub_response($mp_response, &$user, &$entry)
    {
        try {
            $user->last_micropub_response = $mp_response['response'];
            $user->save();

            $entry->micropub_response = $mp_response['response'];
            $entry->micropub_success = 0;

            if (isset($mp_response['headers']['Location'])) {
                $entry->canonical_url = reset($mp_response['headers']['Location']);
                $entry->micropub_success = 1;
            }

            $entry->save();
            return true;
        } catch (PDOException $e) {
            $this->logger->error(
                'Error adding micropub response. ' . $e->getMessage(),
                $mp_response
            );
            return false;
        }
    }

    protected function add_last_micropub_response(array $mp_response, $user): bool
    {
        try {
            $user->last_micropub_response = $mp_response['response'];
            $user->save();
            return true;
        } catch (PDOException $e) {
            $this->logger->error(
                'Error adding last micropub response. ' . $e->getMessage(),
                $mp_response
            );
            return false;
        }
    }

    /**
     * Build Micropub request from the submitted form
     * @param array $data
     * @return array
     */
    protected function build_micropub_request($data)
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
            'visibility' => [$data['visibility']],
        ];

        if ($data['tags']) {
            $properties['category'] = $this->utils->get_category_array($data['tags']);
        }

        return [
            'type' => ['h-entry'],
            'properties' => $properties,
        ];
    }
}

