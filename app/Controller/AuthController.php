<?php
/**
 * Handles authentication and signout.
 *
 * Originally written by Aaron Parecki. gRegor Morrill modified
 * for indiebookclub and Slim Framework 3.
 *
 * @author Aaron Parecki, https://aaronparecki.com
 * @author gRegor Morrill, https://gregorlove.com
 * @copyright 2014 Aaron Parecki
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @see https://github.com/aaronpk/Teacup
 */

declare(strict_types=1);

namespace App\Controller;

use ORM;
use IndieAuth\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController extends Controller
{
    public function debug(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        exit;
    }

    /**
     * Route that starts the authentication process
     */
    public function start(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $params = $request->getQueryParams();

        // Attempt to normalize the 'me' parameter or display an error
        $me = Client::normalizeMeURL($params['me'] ?? '');
        if (false === $me) {
            return $this->errorResponse($response, 'Invalid “me” parameter', 'The URL you entered is not valid.');
        }

        // Prevent logging in with this domain.
        if (strtolower($this->utils->hostname($me)) == $_ENV['IBC_HOSTNAME']) {
            return $this->errorResponse($response, '<i>Inception</i> error', 'No, we cannot go deeper. :] Please log in with <i>your</i> domain name.');
        }

        // Prevent logging in with URL paths
        if (!in_array(parse_url($me, PHP_URL_PATH), ['/', '//'])) {
            return $this->errorResponse($response, 'Invalid “me” parameter', 'URL paths are not currently supported. Please log in with only a domain name.');
        }

        // Restrict the domains that can log in to development environment.
        if (getenv('APP_ENV') !== 'production' && !in_array($this->utils->hostname($me), $this->settings['developer_domains'])) {
            return $this->errorResponse($response, 'Invalid “me” parameter', 'This development instance does not permit logging in with that domain.');
        }

        // The client ID should be the home page of your app.
        Client::$clientID = sprintf('https://%s/', $_ENV['IBC_HOSTNAME']);

        // The redirect URL is where the user will be returned to after they approve the request.
        Client::$redirectURL = $this->utils->getRedirectURL();

        $metadata_endpoint = Client::discoverMetadataEndpoint($me);
        $authorization_endpoint = Client::discoverAuthorizationEndpoint($me);
        $token_endpoint = Client::discoverTokenEndpoint($me);
        $micropub_endpoint = Client::discoverMicropubEndpoint($me);
        $is_micropub_user = ($token_endpoint && $micropub_endpoint && $authorization_endpoint) ? true : false;
        #$authorization_endpoint = false;
        #var_dump($authorization_endpoint); exit;
        #$is_micropub_user = false;

        if ($is_micropub_user) {
            list($authorization_url, $error) = Client::begin($me, 'create profile');
        } else {
            if (!$authorization_endpoint) {
                $authorization_endpoint = 'https://indielogin.com/auth';
            }

            list($authorization_url, $error) = Client::begin($me, false, $authorization_endpoint);
        }

        if ($error) {
            return $this->errorResponse($response, $error['error'], $error['error_description']);
        }

        // Store endpoints in session. Used after authorization to add/update the user.
        $_SESSION['authorization_endpoint'] = $authorization_endpoint;
        $_SESSION['micropub_endpoint'] = $micropub_endpoint;
        $_SESSION['token_endpoint'] = $token_endpoint;

        $user = $this->get_user_by_slug($this->utils->hostname($me));

        // User has logged in before and isn't restarting; can redirect directly to $authorization_url
        if ($user && $user->last_login && !array_key_exists('restart', $params)) {
            return $response->withRedirect($authorization_url, 301);
        }

        return $this->theme->render(
            $response,
            'auth/start',
            compact(
                'me',
                'is_micropub_user',
                'token_endpoint',
                'metadata_endpoint',
                'micropub_endpoint',
                'authorization_endpoint',
                'authorization_url'
            )
        );
    }

    /**
     * Route that handles authentication callback
     */
    public function callback(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        // The client ID should be the home page of your app.
        Client::$clientID = sprintf('https://%s/', $_ENV['IBC_HOSTNAME']);

        // The redirect URL is where the user will be returned to after they approve the request.
        Client::$redirectURL = $this->utils->getRedirectURL();

        $params = $request->getQueryParams();
        list($indieauth_response, $error) = Client::complete($params);

        if ($error) {
            return $this->errorResponse($response, $error['error'], $error['error_description']);
        }

        $me = $indieauth_response['me'];
        $user = $this->get_user_by_slug($this->utils->hostname($me));

        # get the profile name and photo, preferably from the IndieAuth profile response
        $profile = $this->utils->getProfileFromIndieAuth($indieauth_response['response']);

        if (!($profile['name'] && $profile['photo'])) {
            # fallback to the representative h-card for missing fields
            if ($h_card = Client::representativeHCard($me)) {
                $profile = $this->utils->getProfileFromHCard($profile, $h_card);
            }
        }

        # prepare the ORM record with latest info
        $user = $this->utils->setUserData($profile, $user);

        if ($user->id) {
            $user = $this->updateUser($user, $indieauth_response);
        } else {
            $user = $this->createUser($user, $indieauth_response);
        }

        if (!$user) {
            $response = $response->withStatus(500);
            return $this->theme->render($response, '500');
        }

        $this->utils->setAccessToken($indieauth_response);

        if ($micropub_endpoint = $this->utils->session('micropub_endpoint')) {
            $user = $this->updateMicropubConfig($user, $micropub_endpoint);
        }

        if (!$user) {
            $response = $response->withStatus(500);
            return $this->theme->render($response, '500');
        }

        $_SESSION['me'] = $me;
        $_SESSION['user_id'] = $user->id();
        $_SESSION['display_name'] = ($user->name)
            ? $user->name
            : $this->utils->hostname($me);
        $_SESSION['display_photo'] = ($user->photo_url)
            ? $user->photo_url
            : '';

        unset($_SESSION['authorization_endpoint']);
        unset($_SESSION['token_endpoint']);

        return $response->withRedirect('/new', 302);
    }

    /**
     * Route that handles re-authorizing
     */
    public function re_authorize(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $profile = $this->get_user();

        if ($request->isPost()) {
            $data = $request->getParsedBody();

            $me = Client::normalizeMeURL($profile->url);
            $scopes = implode(' ', $data['scopes']);

            // The client ID should be the home page of your app.
            Client::$clientID = sprintf('https://%s/', $_ENV['IBC_HOSTNAME']);

            // The redirect URL is where the user will be returned to after they approve the request.
            Client::$redirectURL = $this->utils->getRedirectURL();

            $authorization_endpoint = Client::discoverAuthorizationEndpoint($me);
            $token_endpoint = Client::discoverTokenEndpoint($me);
            $micropub_endpoint = Client::discoverMicropubEndpoint($me);
            list($authorization_url, $error) = Client::begin($me, $scopes);

            if ($error) {
                return $this->errorResponse($response, $error['error'], $error['error_description']);
            }

            // Store endpoints in session. Used after authorization to add/update the user.
            $_SESSION['authorization_endpoint'] = $authorization_endpoint;
            $_SESSION['micropub_endpoint'] = $micropub_endpoint;
            $_SESSION['token_endpoint'] = $token_endpoint;

            return $response->withRedirect($authorization_url, 302);
        }

        $headline = 'Additional Permission Requested';
        $message = '<p> indiebookclub is requesting permission to delete posts from your site. </p> <p> Click the button below to re-authorize the app. </p>';

        return $this->theme->render(
            $response,
            'auth/re_authorize',
            compact(
                'me',
                'headline',
                'message'
            )
        );
    }

    /**
     * Route that resets the login
     */
    public function reset(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $user = $this->get_user();

        $this->utils->revoke_micropub_token(
            $this->utils->getAccessToken(),
            $user->token_endpoint
        );

        $user->authorization_endpoint = '';
        $user->token_endpoint = '';
        $user->micropub_endpoint = '';
        $user->micropub_media_endpoint = '';
        $user->token_scope = '';
        $user->last_login = null;
        $user->save();

        unset($_SESSION['auth']);
        unset($_SESSION['me']);
        unset($_SESSION['auth_state']);
        unset($_SESSION['user_id']);
        unset($_SESSION['access_token']);

        return $response->withRedirect('/', 302);
    }

    /**
     * Route that signs out the user
     */
    public function signout(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $user = $this->get_user();

        $this->utils->revoke_micropub_token(
            $this->utils->getAccessToken(),
            $user->token_endpoint
        );

        unset($_SESSION['auth']);
        unset($_SESSION['me']);
        unset($_SESSION['auth_state']);
        unset($_SESSION['user_id']);
        unset($_SESSION['access_token']);
        return $response->withRedirect('/', 302);
    }

    private function createUser(ORM $user, array $indieauth_response): ?ORM
    {
        $user->url = $indieauth_response['me'];
        $user->profile_slug = $this->utils->hostname($indieauth_response['me']);
        $user->token_scope = $indieauth_response['response']['scope'] ?? '';
        $user->set_expr('date_created', 'NOW()');
        $user->set_expr('last_login', 'NOW()');

        if ($user->save()) {
            return $user;
        }

        return null;
    }

    private function updateUser(ORM $user, array $indieauth_response): ?ORM
    {
        $user->token_scope = $indieauth_response['response']['scope'] ?? '';
        $user->set_expr('last_login', 'NOW()');

        if ($user->save()) {
            return $user;
        }

        return null;
    }

    private function updateMicropubConfig(ORM $user, string $micropub_endpoint): ?ORM
    {
        $config_response = $this->utils->micropub_get(
            $micropub_endpoint,
            ['q' => 'config'],
            $this->utils->getAccessToken()
        );

        if (array_key_exists('visibility', $config_response['data'])) {
            $user->supported_visibility = json_encode($config_response['data']['visibility']);
        }

        if ($user->save()) {
            return $user;
        }

        return null;
    }

    private function errorResponse($response, string $error, string $errorDescription): ResponseInterface
    {
        return $this->theme->render(
            $response,
            'auth/error',
            compact('error', 'errorDescription')
        );
    }
}

