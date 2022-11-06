<?php
/**
 * Helper utilities used in indiebookclub.
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

namespace App\Helper;

use BarnabyWalters\Mf2 as Mf2helper;
use DateTime;
use DateInterval;
use Mf2;
use PHPMailer\PHPMailer\PHPMailer;

class Utils
{
    /**
     * @var $router
     */
    public $router;

    /**
     * @param $router
     */
    public function __construct($router)
    {
        $this->router = $router;
    }

    /**
     * Get a value from $_SESSION
     * @param string $key
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function session(string $key)
    {
        if (array_key_exists($key, $_SESSION)) {
            return $_SESSION[$key];
        }

        return null;
    }

    /**
     * Sanitize a value for display in HTML
     */
    public function sanitize(?string $value = null): string
    {
        if (!$value) {
            return '';
        }

        if (strpos($value, "\r") !== false) {
            $value = str_replace("\r", "\n", $value); // normalize to LF
        }

        $pos = strpos($value, "\n");

        if ($pos !== false) {
            $value = str_replace(["\n\n", "\n"], ' ', $value);
        }

        return htmlentities(
            trim(strip_tags($value)),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    /**
     * Get the redirect URL for authorization callback
     */
    public function getRedirectURL(): string
    {
        return $_ENV['IBC_BASE_URL'] . $this->router->pathFor('auth_callback');
    }

    /**
     * Get the client ID
     */
    public function getClientID(): string
    {
        return trim($_ENV['IBC_BASE_URL'], '/');
    }

    /**
     * Get the hostname from a URL
     */
    public function hostname(string $url): string
    {
        return preg_replace('#^www\.(.+\.)#i', '$1', strtolower(parse_url($url, PHP_URL_HOST)));
    }

    public function get_read_status_options(): array
    {
        return [
            'to-read' => 'Want to read',
            'reading' => 'Currently reading',
            'finished' => 'Finished reading',
        ];
    }

    /**
     * Get a human-friendly read status
     */
    public function get_read_status_for_humans(string $read_status): string
    {
        switch ($read_status) {
            case 'finished':
                $text = 'Finished reading';
            break;

            case 'reading':
                $text = 'Currently reading';
            break;

            case 'to-read':
            default:
                $text = 'Want to read';
            break;
        }

        return $text;
    }

    /**
     * Normalize a string of text with provided separator
     *
     * Removes extra whitespace between parts of the input string.
     */
    public function normalizeSeparatedString(string $input, string $separator = ','): string
    {
        $parts = explode($separator, $input);
        $parts = array_map('trim', $parts);
        $parts = array_filter($parts);
        return implode($separator, $parts);
    }

    /**
     * Convert a comma-separated string of categories to an array
     */
    public function get_category_array(string $category): array
    {
        return explode(',', $this->normalizeSeparatedString($category));
    }

    /**
     * Get list of visibility options
     *
     * This depends on what the Micropub endpoint indicates
     * it supports.
     */
    public function get_visibility_options(array $user): array
    {
        $supported = $user['supported_visibility'] ?? null;
        if (!$supported) {
            return ['Public'];
        }

        $supported = json_decode($supported);
        $options = [];
        foreach (['public', 'private', 'unlisted'] as $value) {
            if (in_array($value, $supported)) {
                $options[] = ucfirst($value);
            }
        }

        if ($options) {
            return $options;
        }

        # IBC does not support any of the listed options
        return ['Public'];
    }

    /**
     * Set access_token in the $_SESSION
     */
    public function setAccessToken(array $indieauth_response): void
    {
        $access_token = $indieauth_response['response']['access_token'] ?? null;
        if ($access_token) {
            $_SESSION['auth']['access_token'] = $access_token;
        }
    }

    /**
     * Get access_token from $_SESSION
     */
    public function getAccessToken()
    {
        if (isset($_SESSION['auth']['access_token'])) {
            return $_SESSION['auth']['access_token'];
        }

        return '';
    }

    /**
     * Attempt to get the user's profile from the IndieAuth response
     */
    public function getProfileFromIndieAuth(array $indieauth_response): array
    {
        $profile = array_fill_keys(['name', 'photo'], '');

        if (array_key_exists('profile', $indieauth_response)) {
            $name = $indieauth_response['profile']['name'] ?? null;
            $photo = $indieauth_response['profile']['photo'] ?? null;

            if ($name) {
                $profile['name'] = $name;
            }

            if ($photo) {
                $profile['photo'] = $photo;
            }
        }

        return $profile;
    }

    /**
     * Attempt to get the user's profile from an h-card
     */
    public function getProfileFromHCard(array $profile, array $h_card): array
    {
        if (!$profile['name'] && Mf2helper\hasProp($h_card, 'name')) {
            $profile['name'] = Mf2helper\getPlaintext($h_card, 'name');
        }

        if (!$profile['photo'] && Mf2helper\hasProp($h_card, 'photo')) {
            $profile['photo'] = Mf2helper\getPlaintext($h_card, 'photo');
        }

        return $profile;
    }

    /**
     * Append query params to a URL
     */
    public function build_url($url, $params = [])
    {
        if (!$params) {
            return $url;
        }

        $join_char = '?';
        if (parse_url($url, PHP_URL_QUERY)) {
            $join_char = '&';
        }

        return $url . $join_char . http_build_query($params);
    }

    public function hasMicropubDelete(string $scopes): bool
    {
        $scopes = explode(' ', $this->normalizeSeparatedString($scopes, ' '));
        return in_array('delete', $scopes);
    }

    /**
     * Send a Micropub POST request
     * @param string $endpoint
     * @param array $params
     * @param string $access_token
     * @param bool $json
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function micropub_post($endpoint, $params, $access_token, $json = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);

        // Send the access token in both the header and post body to support more clients
        // https://github.com/aaronpk/Quill/issues/4
        // http://indiewebcamp.com/irc/2015-02-14#t1423955287064
        $httpheaders = ['Authorization: Bearer ' . $access_token];

        if (!$json) {
            $params['access_token'] = $access_token;

            if (!array_key_exists('action', $params)) {
                $params['h'] = 'entry';
            }
        }

        if ($json) {
            $httpheaders[] = 'Accept: application/json';
            $httpheaders[] = 'Content-type: application/json';
            $post = json_encode($params);
        } else {
            $post = http_build_query($params);
            $post = preg_replace('/%5B[0-9]+%5D/', '%5B%5D', $post); // change [0] to []
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheaders);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($ch);
        $sent_headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header_str = trim(substr($response, 0, $header_size));
        $request = $sent_headers . (is_string($post) ? $post : http_build_query($post));

        return [
            'request' => $request,
            'response' => $response,
            'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'headers' => $this->parse_headers($header_str),
            'error' => curl_error($ch),
            'curlinfo' => curl_getinfo($ch)
        ];
    }

    /**
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function micropub_get(
        string $endpoint,
        string $access_token,
        array $params = []
    ) {
        $endpoint = $this->build_url($endpoint, $params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $data = [];

        if ($response) {
            $data = @json_decode($response, true) ?? [];
        }

        $error = curl_error($ch);

        return [
            'response' => $response,
            'data' => $data,
            'error' => $error,
            'curlinfo' => curl_getinfo($ch)
        ];
    }

    /**
     * Parse HTTP headers
     * @param string $headers
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function parse_headers($headers)
    {
        $retVal = [];
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $headers));
        foreach ($fields as $field) {
            if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
                $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m) {
                    return strtoupper($m[0]);
                }, strtolower(trim($match[1])));
                // If there's already a value set for the header name being returned, turn it into an array and add the new value
                if (isset($retVal[$match[1]])) {
                    $retVal[$match[1]][] = trim($match[2]);
                } else {
                    $retVal[$match[1]] = [trim($match[2])];
                }
            }
        }
        return $retVal;
    }

    /**
     * Revoke an access token
     *
     * Note: authentication is not yet supported for revocation
     * endpoint
     *
     * @see https://indieauth.spec.indieweb.org/#token-revocation-request
     * @see https://github.com/aaronpk/Quill/commit/bb0752a72692d03b61f1719dca2a7cdc2b3052cc
     */
    public function send_token_revocation(
        string $endpoint,
        string $token
    ) {
        $fields = compact('token');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_exec($ch);
    }

    /**
     * LEGACY
     * Revoke an access token
     *
     * Earlier versions of the IndieAuth specification
     * sent revocation requests to the token endpoint with
     * action=revoke.
     *
     * Should only use this method if site specifies a token
     * endpoint but no revocation endpoint.
     *
     * @see https://indieauth.spec.indieweb.org/#token-revocation-request
     * @see https://github.com/aaronpk/Quill/commit/bb0752a72692d03b61f1719dca2a7cdc2b3052cc
     */
    public function send_legacy_token_revocation(
        string $endpoint,
        string $token
    ) {
        $action = 'revoke';
        $fields = compact('action', 'token');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_exec($ch);
    }

    /**
     * Parse a URL for read-of microformats
     */
    public function parse_read_of(string $url): array
    {
        $result = array_fill_keys(['title', 'authors', 'uid'], '');
        $url = filter_var($url, FILTER_SANITIZE_URL);

        if (!$url) {
            return $result;
        }

        $mf = Mf2\fetch($url);

        if (!Mf2helper\isMicroformatCollection($mf)) {
            return $result;
        }

        $entries = Mf2helper\findMicroformatsByType($mf, 'h-entry');

        if ($entries) {
            $entry = reset($entries);

            if (Mf2helper\hasProp($entry, 'read-of')) {
                $read_of = reset($entry['properties']['read-of']);

                if (Mf2helper\isMicroformat($read_of)) {
                    $result['title'] = (Mf2helper\hasProp($read_of, 'name')) ? Mf2helper\getPlaintext($read_of, 'name') : Mf2helper\toPlaintext($read_of);
                    $result['authors'] = Mf2helper\getPlaintext($read_of, 'author');
                    $result['uid'] = Mf2helper\getPlaintext($read_of, 'uid');
                } elseif (is_string($read_of)) {
                    $result['title'] = $read_of;
                }

                return $result;
            }
        }

        // at this point, either no h-entry was found, or was found and did not have read-of property
        // parse for h-cite

        if ($citations = Mf2helper\findMicroformatsByType($mf, 'h-cite')) {
            $cite = reset($citations);
            $result['title'] = Mf2helper\getPlaintext($cite, 'name');
            $result['authors'] = Mf2helper\getPlaintext($cite, 'author');
            $result['uid'] = Mf2helper\getPlaintext($cite, 'uid');
        }

        return $result;
    }

    public function notify_admin(
        string $message,
        string $subject = 'indiebookclub admin notification'
    ): bool {
        $from = 'no-reply@' . $_ENV['IBC_HOSTNAME'];
        $mailer = new PHPMailer();
        $mailer->CharSet = 'UTF-8';
        $mailer->isSendmail();
        $mailer->setFrom($from, 'indiebookclub admin');
        $mailer->addAddress($_ENV['IBC_EMAIL']);
        $mailer->Subject = $subject;
        $mailer->Body = $message;
        return $mailer->send();
    }
}

