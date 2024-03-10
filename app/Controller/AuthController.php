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

use IndieAuth\Client;
use Psr\Http\Message\{
    ResponseInterface,
    ServerRequestInterface
};

class AuthController extends Controller
{
    private function initClient(): void
    {
        // The client ID should be the home page of your app.
        Client::$clientID = sprintf('https://%s/', $_ENV['IBC_HOSTNAME']);

        // The redirect URL is where the user will be returned to after they approve the request.
        Client::$redirectURL = $_ENV['IBC_BASE_URL'] . $this->router->pathFor('auth_callback');
    }

    /**
     * Start the authentication process
     */
    public function start(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ) {
        // Attempt to normalize the 'me' parameter or display an error
        $me = $request->getQueryParam('me', '');
        $me = Client::normalizeMeURL(trim($me));
        if (false === $me) {
            return $this->httpErrorResponse(
                $response,
                'The URL you entered is not valid.'
            );
        }

        if ($request->getQueryParam('debug-me')) {
            return $this->httpErrorResponse(
                $response,
                sprintf('Normalized URL: %s', $me),
                'Debugging'
            );
        }

        $hostname = strtolower($this->utils->hostname($me));

        // Prevent logging in with this domain.
        if ($hostname == $_ENV['IBC_HOSTNAME']) {
            return $this->httpErrorResponse(
                $response,
                'No, we cannot go deeper. Please log in with <i>your</i> domain name. :]',
                '<i>Inception</i> error'
            );
        }

        // Prevent logging in with URL paths
        if (!in_array(parse_url($me, PHP_URL_PATH), ['/', '//'])) {
            return $this->httpErrorResponse(
                $response,
                'URL paths like example.com/username are not currently supported. Please log in with only a domain name.'
            );
        }

        // Restrict the domains that can log in to development environment.
        if ($_ENV['APP_ENV'] !== 'production' && !in_array($hostname, $this->settings['developer_domains'])) {
            return $this->httpErrorResponse(
                $response,
                'This development instance does not permit logging in with that domain.'
            );
        }

        $this->initClient();

        $metadata_endpoint = Client::discoverMetadataEndpoint($me);
        $authorization_endpoint = Client::discoverAuthorizationEndpoint($me);
        $token_endpoint = Client::discoverTokenEndpoint($me);
        $revocation_endpoint = Client::discoverRevocationEndpoint($me);
        $micropub_endpoint = Client::discoverMicropubEndpoint($me);
        $is_micropub_user = ($authorization_endpoint && $token_endpoint && $micropub_endpoint);

        if ($is_micropub_user) {
            list($authorization_url, $error) = Client::begin($me, 'create profile');
        } else {
            if (!$authorization_endpoint) {
                $authorization_endpoint = 'https://indielogin.com/auth';
            }

            list($authorization_url, $error) = Client::begin($me, false, $authorization_endpoint);
        }

        if ($error) {
            return $this->httpErrorResponse(
                $response,
                sprintf('%s (%s)', $error['error_description'], $error['error'])
            );
        }

        // Store endpoints in session. Used after authorization to add/update the user.
        $_SESSION['authorization_endpoint'] = $authorization_endpoint;
        $_SESSION['micropub_endpoint'] = $micropub_endpoint;
        $_SESSION['token_endpoint'] = $token_endpoint;
        $_SESSION['revocation_endpoint'] = $revocation_endpoint;

        $user = $this->User->findBySlug($hostname);
        if ($user) {
            // User has logged in before and isn't restarting; can redirect directly to $authorization_url
            if ($user['last_login'] && !$request->getQueryParam('restart')) {
                return $response->withRedirect($authorization_url, 302);
            }
        }

        return $this->view->render(
            $response,
            'pages/auth/start.twig',
            compact(
                'me',
                'is_micropub_user',
                'metadata_endpoint',
                'authorization_endpoint',
                'micropub_endpoint',
                'token_endpoint',
                'revocation_endpoint',
                'authorization_url'
            )
        );
    }

    /**
     * Handle authentication callback
     */
    public function callback(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ) {
        $this->initClient();

        $params = $request->getQueryParams();
        list($indieauth_response, $error) = Client::complete($params);

        if ($error) {
            return $this->httpErrorResponse(
                $response,
                sprintf('%s (%s)', $error['error_description'], $error['error'])
            );
        }

        # get the profile name and photo, preferably from the IndieAuth profile response
        $me = $indieauth_response['me'];
        $profile = $this->utils->getProfileFromIndieAuth($indieauth_response['response']);

        if (!($profile['name'] && $profile['photo'])) {
            # fallback to the representative h-card for missing fields
            if ($h_card = Client::representativeHCard($me)) {
                $profile = $this->utils->getProfileFromHCard($profile, $h_card);
            }
        }

        $supported_visibility = '';
        if ($micropub_endpoint = $this->utils->session('micropub_endpoint')) {
            # get config from the Micropub endpoint
            $config_response = $this->utils->micropub_get(
                $micropub_endpoint,
                $this->utils->getAccessToken(),
                ['q' => 'config'],
            );

            if (array_key_exists('visibility', $config_response['data'])) {
                $supported_visibility = json_encode($config_response['data']['visibility']);
            }
        }

        $hostname = strtolower($this->utils->hostname($me));
        $user = $this->User->findBySlug($hostname);

        $user_data = [
            'name' => $profile['name'],
            'photo_url' => $profile['photo'],
            'authorization_endpoint' => $this->utils->session('authorization_endpoint'),
            'token_endpoint' => $this->utils->session('token_endpoint'),
            'revocation_endpoint' => $this->utils->session('revocation_endpoint'),
            'micropub_endpoint' => $this->utils->session('micropub_endpoint'),
            'supported_visibility' => $supported_visibility,
            'token_scope' => $indieauth_response['response']['scope'] ?? '',
            'last_login' => true,
        ];

        if ($user) {
            # update existing user
            $user = $this->User->update((int) $user['id'], $user_data);
        } else {
            # add new user
            $user_data['url'] = $me;
            $user_data['profile_slug'] = $hostname;
            $user = $this->User->add($user_data);
        }

        if (!$user) {
            $response = $response->withStatus(500);
            return $this->view->render($response, 'pages/500.twig');
        }

        $this->utils->setAccessToken($indieauth_response);

        $_SESSION['me'] = $me;
        $_SESSION['hostname'] = $hostname;
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['display_photo'] = $user['display_photo'];

        unset($_SESSION['authorization_endpoint']);
        unset($_SESSION['micropub_endpoint']);
        unset($_SESSION['token_endpoint']);

        # default redirect
        $redirect_url = $this->router->pathFor('new');

        if ($signin_redirect = $this->utils->session('signin_redirect')) {
            # override with redirect that was previously sanitized and verified
            $redirect_url = $signin_redirect;
            unset($_SESSION['signin_redirect']);
        }

        return $response->withRedirect($redirect_url, 302);
    }

    /**
     * Route that handles re-authorizing
     */
    public function re_authorize(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ) {
        $user = $this->User->get($this->utils->session('user_id'));

        if ($request->isPost()) {
            $data = $request->getParsedBody();

            $this->initClient();

            $me = Client::normalizeMeURL($user['url']);
            $scopes = implode(' ', $data['scopes']);

            $metadata_endpoint = Client::discoverMetadataEndpoint($me);
            $authorization_endpoint = Client::discoverAuthorizationEndpoint($me);
            $token_endpoint = Client::discoverTokenEndpoint($me);
            $micropub_endpoint = Client::discoverMicropubEndpoint($me);
            list($authorization_url, $error) = Client::begin($me, $scopes);

            if ($error) {
                return $this->httpErrorResponse(
                    $response,
                    sprintf('%s (%s)', $error['error_description'], $error['error'])
                );
            }

            // Store endpoints in session. Used after authorization to add/update the user.
            $_SESSION['authorization_endpoint'] = $authorization_endpoint;
            $_SESSION['micropub_endpoint'] = $micropub_endpoint;
            $_SESSION['token_endpoint'] = $token_endpoint;

            return $response->withRedirect($authorization_url, 302);
        }

        $message = '<p> indiebookclub is requesting permission to delete posts from your site. </p> <p> Click the button below to re-authorize the app. </p>';

        $current_scopes = explode(' ', $user['token_scope']);

        return $this->view->render(
            $response,
            'pages/auth/re-authorize.twig',
            compact(
                'message',
                'current_scopes'
            )
        );
    }

    /**
     * Reset endpoints then redirect to signout
     */
    public function reset(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ) {
        $this->User->reset($this->utils->session('user_id'));
        return $response->withRedirect($this->router->pathFor('signout'), 302);
    }

    /**
     * Revoke access token, if applicable, and signout
     */
    public function signout(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ) {
        if (!$this->utils->session('user_id')) {
            # already signed out
            return $response->withRedirect('/', 302);
        }

        $user = $this->User->get($this->utils->session('user_id'));

        if ($user['revocation_endpoint']) {
            $this->utils->send_token_revocation(
                $user['revocation_endpoint'],
                $this->utils->getAccessToken()
            );
        } elseif ($user['token_endpoint']) {
            $this->utils->send_legacy_token_revocation(
                $user['token_endpoint'],
                $this->utils->getAccessToken()
            );
        }

        $keys = [
            'auth',
            'me',
            'hostname',
            'user_id',
            'display_name',
            'display_photo',
        ];

        foreach ($keys as $Key) {
            unset($_SESSION[$Key]);
        }

        return $response->withRedirect('/', 302);
    }

    private function httpErrorResponse(
        ResponseInterface $response,
        string $message = null,
        string $short_title = null,
        int $status = 400
    ): ResponseInterface {
        $response = $response->withStatus($status);
        return $this->view->render(
            $response,
            'pages/400.twig',
            compact('short_title', 'message')
        );
    }
}

