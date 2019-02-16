<?php
namespace App\Controller;

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

use \ORM;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends Controller
{
    /**
     * Route that starts the authentication process
     */
    public function start(Request $request, Response $response, array $args) {
        $params = $request->getQueryParams();

        // Attempt to normalize the 'me' parameter or display an error.
        if (!array_key_exists('me', $params) || !($me = \IndieAuth\Client::normalizeMeURL($params['me']))) {
            return $this->theme->render(
                $response,
                'auth/error',
                [
                    'error' => 'Invalid “me” parameter',
                    'errorDescription' => 'The URL you entered is not valid.'
                ]
            );
        }

        // Prevent logging in with this domain.
        if (strtolower($this->utils->hostname($me)) == getenv('IBC_HOSTNAME')) {
            return $this->theme->render(
                $response,
                'auth/error',
                [
                    'error' => '<i>Inception</i> error',
                    'errorDescription' => 'No, we cannot go deeper. :] Please log in with <i>your</i> domain name.'
                ]
            );
        }

        // Prevent logging in with URL paths
        if (!in_array(parse_url($me, PHP_URL_PATH), ['/', '//'])) {
            return $this->theme->render(
                $response,
                'auth/error',
                [
                    'error' => 'Invalid “me” parameter',
                    'errorDescription' => 'URL paths are not currently supported. Please log in with only a domain name.'
                ]
            );
        }

        // Restrict the domains that can log in to development environment.
        if (getenv('APP_ENV') !== 'production' && $this->utils->hostname($me) !== $this->settings['developer_domain']) {
            return $this->theme->render(
                $response,
                'auth/error',
                [
                    'error' => 'Invalid “me” parameter',
                    'errorDescription' => 'This development instance does not permit logging in with that domain.'
                ]
            );
        }

        // Discover endpoints and determine if this is a micropub user.
        $authorization_endpoint = \IndieAuth\Client::discoverAuthorizationEndpoint($me);
        $token_endpoint = \IndieAuth\Client::discoverTokenEndpoint($me);
        $micropub_endpoint = \IndieAuth\Client::discoverMicropubEndpoint($me);
        $is_micropub_user = ($token_endpoint && $micropub_endpoint && $authorization_endpoint) ? true : false;

        $_SESSION['attempted_me'] = $me;
        $_SESSION['auth_state'] = \IndieAuth\Client::generateStateParameter();
        $_SESSION['redirect_after_login'] = '/new';

        if ($is_micropub_user) {
            $authorization_url = \IndieAuth\Client::buildAuthorizationURL(
                $authorization_endpoint,
                $me,
                $this->utils->getRedirectURL(),
                $this->utils->getClientID(),
                $_SESSION['auth_state'],
                'create'
            );
            $_SESSION['authorization_endpoint'] = $authorization_endpoint;
            $_SESSION['micropub_endpoint'] = $micropub_endpoint;
            $_SESSION['token_endpoint'] = $token_endpoint;
        } else {
            $authorization_url = \IndieAuth\Client::buildAuthorizationURL(
                'https://indieauth.com/auth',
                $me,
                $this->utils->getRedirectURL(),
                $this->utils->getClientID(),
                $_SESSION['auth_state']
            );
        }

        $h_card = \IndieAuth\Client::representativeHCard($me);
        $user = $this->get_user_by_slug($this->utils->hostname($me));

        if ($user && $user->last_login && !array_key_exists('restart', $params)) {
            $this->utils->add_hcard_info($user, $h_card);
            $user->micropub_endpoint = $micropub_endpoint;
            $user->authorization_endpoint = $authorization_endpoint;
            $user->token_endpoint = $token_endpoint;
            $user->type = $micropub_endpoint ? 'micropub' : 'local';
            $user->save();

            return $response->withRedirect($authorization_url, 301);
        }

        if (!$user) {
            $user = ORM::for_table('users')->create();
        }

        $this->utils->add_hcard_info($user, $h_card);
        $user->url = $me;
        $user->profile_slug = $this->utils->hostname($me);
        $user->date_created = date('Y-m-d H:i:s');
        $user->micropub_endpoint = $micropub_endpoint;
        $user->authorization_endpoint = $authorization_endpoint;
        $user->token_endpoint = $token_endpoint;
        $user->type = $micropub_endpoint ? 'micropub' : 'local';
        $user->save();

        return $this->theme->render(
            $response,
            'auth/start',
            compact(
                'me',
                'is_micropub_user',
                'token_endpoint',
                'micropub_endpoint',
                'authorization_endpoint',
                'authorization_url'
            )
        );
    }

    /**
     * Route that handles authentication callback
     */
    public function callback(Request $request, Response $response, array $args) {
        $params = $request->getQueryParams();

        // Missing auth state in session; start the login again.
        if (!array_key_exists('auth_state', $_SESSION)) {
            return $this->theme->render(
                $response,
                'auth/error',
                [
                    'error' => 'Missing session state',
                    'errorDescription' => 'Something went wrong, please try signing in again, and make sure cookies are enabled for this domain.'
                ]
            );
        }

        if (!array_key_exists('code', $params) || trim($params['code']) == '') {
            return $this->theme->render(
                $response,
                'auth/error',
                [
                    'error' => 'Missing authorization code',
                    'errorDescription' => 'No authorization code was provided in the request.'
                ]
            );
        }

        // Verify the state came back and matches what we set in the session
        // Should only fail for malicious attempts, ok to show a not as nice error message
        if (!array_key_exists('state', $params)) {
            return $this->theme->render(
                $response,
                'auth/error',
                [
                    'error' => 'Missing state parameter',
                    'errorDescription' => 'No state parameter was provided in the request. This shouldn’t happen. It is possible this is a malicious authorization attempt, or your authorization server failed to pass back the “state” parameter.'
                ]
            );
        }

        if ($params['state'] != $_SESSION['auth_state']) {
            return $this->theme->render(
                $response,
                'auth/error',
                [
                    'error' => 'Invalid state',
                    'errorDescription' => 'The state parameter provided did not match the state provided at the start of authorization. This is most likely caused by a malicious authorization attempt.'
                ]
            );
        }

        unset($_SESSION['auth_state']);

        if (!isset($_SESSION['attempted_me'])) {
            return $this->theme->render(
                $response,
                'auth/error',
                [
                    'error' => 'Missing data',
                    'errorDescription' => 'We forgot who was logging in. It’s possible you took too long to finish signing in, or something got mixed up by signing in on another tab.'
                ]
            );
        }

        $me = $_SESSION['attempted_me'];

        // Now the basic sanity checks have passed. Time to start providing more helpful messages when there is an error.
        // An authorization code is in the query string, and we want to exchange that for an access token at the token endpoint.

        $authorization_endpoint = isset($_SESSION['authorization_endpoint']) ? $_SESSION['authorization_endpoint'] : false;
        $token_endpoint = isset($_SESSION['token_endpoint']) ? $_SESSION['token_endpoint'] : false;
        $micropub_endpoint = isset($_SESSION['micropub_endpoint']) ? $_SESSION['micropub_endpoint'] : false;

        unset($_SESSION['authorization_endpoint']);
        unset($_SESSION['token_endpoint']);
        unset($_SESSION['micropub_endpoint']);

        $skipDebugScreen = false;

        if ($token_endpoint) {
            // Exchange auth code for an access token
            $token = \IndieAuth\Client::getAccessToken(
                $token_endpoint,
                $params['code'],
                $me,
                $this->utils->getRedirectURL(),
                $this->utils->getClientID(),
                true
            );

            // Valid access token was returned. Verify `me` matches expected domain.
            if ($this->utils->hasProperty($token['auth'], ['me', 'access_token', 'scope'])) {
                if (parse_url($token['auth']['me'], PHP_URL_HOST) != parse_url($me, PHP_URL_HOST)) {
                    return $this->theme->render(
                        $response,
                        'auth/error',
                        [
                            'error' => 'Invalid user',
                            'errorDescription' => 'The user URL that was returned in the access token did not match the domain of the user signing in.'
                        ]
                    );
                }

                // Verify that the returned `me` does not have paths.
                if (!in_array(parse_url($token['auth']['me'], PHP_URL_PATH), ['/', '//'])) {
                    return $this->theme->render(
                        $response,
                        'auth/error',
                        [
                            'error' => 'Invalid profile URL',
                            'errorDescription' => 'The authorization endpoint returned a profile URL with a path. URL paths are not currently supported.'
                        ]
                    );
                }

                // User is now signed in.
                $_SESSION['auth'] = $token['auth'];
                $_SESSION['me'] = $token['auth']['me'];
            }

        } else {
            // No token endpoint was discovered, instead, verify the auth code at the auth server or with indieauth.com
            // Never show the intermediate login confirmation page if we just authenticated them instead of got authorization
            $skipDebugScreen = true;

            if (!$authorization_endpoint) {
                $authorization_endpoint = 'https://indieauth.com/auth';
            }

            $token['auth'] = \IndieAuth\Client::verifyIndieAuthCode(
                $authorization_endpoint,
                $params['code'],
                $me,
                $this->utils->getRedirectURL(),
                $this->utils->getClientID()
            );

            if ($this->utils->hasProperty($token['auth'], 'me')) {
                $token['response'] = '';
                $token['auth']['scope'] = '';
                $token['auth']['access_token'] = '';
                $_SESSION['auth'] = $token['auth'];
                $_SESSION['me'] = $token['auth']['me'];
            }
        }

        // Verify the login actually succeeded.
        if (!$this->utils->hasProperty($token['auth'], 'me')) {
            return $this->theme->render(
                $response,
                'auth/error',
                [
                    'error' => 'Unable to verify the sign-in attempt',
                    'errorDescription' => ''
                ]
            );
        }

        $user = $this->get_user_by_slug($this->utils->hostname($me));

        if (!$user) {
            $user = ORM::for_table('users')->create();
            $user->url = $me;
            $user->profile_slug = $this->utils->hostname($me);
            $user->date_created = date('Y-m-d H:i:s');
        }

        if ($user->last_login) {
            $skipDebugScreen = true;
        }

        $user->micropub_endpoint = $micropub_endpoint;
        $user->token_scope = $token['auth']['scope'];
        $user->set_expr('last_login', 'NOW()');
        $user->save();
        $_SESSION['user_id'] = $user->id();

        if ($skipDebugScreen) {
            return $response->withRedirect($_SESSION['redirect_after_login'], 301);
        }

        return $this->theme->render(
            $response,
            'auth/callback',
            [
                'me' => $me,
                'tokenEndpoint' => $token_endpoint,
                'auth' => $token['auth'],
                'response' => $token['response'],
                'curl_error' => (array_key_exists('error', $token) ? $token['error'] : false),
                'redirect' => $_SESSION['redirect_after_login']
            ]
        );
    }

    /**
     * Route that resets the login
     */
    public function reset(Request $request, Response $response, array $args) {
        $user = $this->get_user();

        $this->utils->revoke_micropub_token(
            $this->utils->get_access_token(),
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
    public function signout(Request $request, Response $response, array $args) {
        $user = $this->get_user();

        $this->utils->revoke_micropub_token(
            $this->utils->get_access_token(),
            $user->token_endpoint
        );

        unset($_SESSION['auth']);
        unset($_SESSION['me']);
        unset($_SESSION['auth_state']);
        unset($_SESSION['user_id']);
        unset($_SESSION['access_token']);
        return $response->withRedirect('/', 302);
    }
}

