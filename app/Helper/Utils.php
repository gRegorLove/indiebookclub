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
use ORM;

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
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function hasProperty($input, $property, $default=null)
    {
        if (is_array($property)) {
            $result = true;
            foreach ($property as $key) {
                $result = $result && array_key_exists($key, $input);
            }
            return $result;
        } else {
            if (is_array($input) && array_key_exists($property, $input) && $input[$property]) {
                return $input[$property];
            } elseif (is_object($input) && property_exists($input, $property) && $input->$property) {
                return $input->$property;
            }
            return $default;
        }
    }

    /**
     * Sanitize a value for display in HTML
     * @param string $value
     */
    public function sanitize($value)
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
     * Add `selected` to select form options
     * @param string $field
     * @param string $value
     * @param bool $output = true, echo the value; false, return the value
     */
    public function markSelected($field, $value, $output = true)
    {
        $return_value = ' selected';

        if ((is_array($field) && in_array($value, $field) ) || ($field == $value)) {
            if ($output) {
                echo $return_value;
            } else {
                return $return_value;
            }
        }
    }

    /**
     * Add `checked` to radio/checkbox form fields
     * @param string $field
     * @param string $value
     * @param bool $output = true, echo the value; false, return the value
     */
    public function markChecked($field, $value, $output = true)
    {
        $return_value = ' checked';

        if ((is_array($field) && in_array($value, $field) ) || ($field == $value)) {
            if ($output) {
                echo $return_value;
            } else {
                return $return_value;
            }
        }
    }

    /**
     * Get the redirect URL for authorization callback
     */
    public function getRedirectURL()
    {
        return getenv('IBC_BASE_URL') . $this->router->pathFor('auth_callback');
    }

    /**
     * Get the client ID
     */
    public function getClientID()
    {
        return trim(getenv('IBC_BASE_URL'), '/');
    }

    /**
     * Get the hostname from a URL
     * @param string $url
     */
    public function hostname($url)
    {
        return preg_replace('#^www\.(.+\.)#i', '$1', strtolower(parse_url($url, PHP_URL_HOST)));
    }

    /**
     * Get a human-friendly read status
     * @param string $read_status
     */
    public function get_read_status_for_humans($read_status)
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
     * Get the microformat read-status property
     * @param string $read_status
     */
    public function get_read_status_microformat($read_status)
    {
        return sprintf('<data class="p-read-status" value="%s">%s</data>',
            $read_status,
            $this->get_read_status_for_humans($read_status)
        );
    }

    /**
     * Get the microformat classes for the URL
     */
    public function get_url_microformats($entry)
    {
        return ($entry->canonical_url) ? 'u-url u-uid' : 'u-url';
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
     * @param string $category
     */
    public function get_category_array($category)
    {
        return explode(',', $this->normalizeSeparatedString($category));
    }

    /**
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function tz_seconds_to_offset($seconds)
    {
        return ($seconds < 0 ? '-' : '+') . sprintf('%02d:%02d', abs($seconds/60/60), ($seconds/60)%60);
    }

    /**
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function tz_offset_to_seconds($offset)
    {
        if (preg_match('/([+-])(\d{2}):?(\d{2})/', $offset, $match)) {
            $sign = ($match[1] == '-' ? -1 : 1);
            return (($match[2] * 60 * 60) + ($match[3] * 60)) * $sign;
        }

        return 0;
    }

    /**
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function get_entry_date($entry)
    {
        $date = new DateTime($entry->published);

        if ($entry->tz_offset > 0) {
            $date->add(new DateInterval('PT' . $entry->tz_offset . 'S'));
        } elseif ($entry->tz_offset < 0) {
            $date->sub(new DateInterval('PT' . abs($entry->tz_offset) . 'S'));
        }

        $tz = $this->tz_seconds_to_offset($entry->tz_offset);
        return new DateTime($date->format('Y-m-d H:i:s') . $tz);
    }

    /**
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function get_entry_url($entry, $user)
    {
        if ($entry->canonical_url) {
            return $entry->canonical_url;
        } elseif ($user) {
            return $this->router->pathFor('entry', ['domain' => $user->profile_slug, 'entry' => $entry->id]);
        } else {
            return $this->router->pathFor('entry', ['domain' => $entry->user_profile_slug, 'entry' => $entry->id]);
        }

        return '';
    }

    public function get_visibility_options($user)
    {
        if (!$user->supported_visibility) {
            return ['Public'];
        }

        $supported = json_decode($user->supported_visibility);

        foreach (['public', 'private', 'unlisted'] as $value) {
            if (in_array($value, $supported)) {
                $options[] = ucfirst($value);
            }
        }

        # IBC does not support any of the listed options
        if (!$options) {
            $options = ['Public'];
        }

        return $options;
    }

    public function setAccessToken(array $indieauth_response)
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

    public function setUserData(?ORM $user = null, array $h_card = []): ?ORM
    {
        $authorization_endpoint = $this->session('authorization_endpoint');
        $token_endpoint = $this->session('token_endpoint');
        $micropub_endpoint = $this->session('micropub_endpoint');

        if (!$user) {
            $user = ORM::for_table('users')->create();
        }

        $user->type = $micropub_endpoint ? 'micropub' : 'local';

        if ($authorization_endpoint) {
            $user->authorization_endpoint = $authorization_endpoint;
        }

        if ($token_endpoint) {
            $user->token_endpoint = $token_endpoint;
        }

        if ($micropub_endpoint) {
            $user->micropub_endpoint = $micropub_endpoint;
        }

        if ($h_card) {
            if (Mf2helper\hasProp($h_card, 'name')) {
                $user->name = Mf2helper\getPlaintext($h_card, 'name');
            }

            if (Mf2helper\hasProp($h_card, 'photo')) {
                $user->photo_url = Mf2helper\getPlaintext($h_card, 'photo');
            }
        }

        $user->set_expr('date_created', 'NOW()');
        $user->set_expr('last_login', 'NOW()');

        return $user;
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
     * Not used currently
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function micropub_get($endpoint, $params, $access_token)
    {
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
     * Not used currently
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function get_micropub_config($user)
    {
        $targets = [];

        $r = micropub_get(
            $user->micropub_endpoint,
            ['q' => 'config'],
            $this->getAccessToken()
        );

        if ($r['data'] && is_array($r['data']) && array_key_exists('media-endpoint', $r['data'])) {
            $user->micropub_media_endpoint = $r['data']['media-endpoint'];
            $user->save();
        }

        return [
            'response' => $r
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
     * @see https://github.com/aaronpk/Quill/commit/bb0752a72692d03b61f1719dca2a7cdc2b3052cc
     */
    public function revoke_micropub_token($access_token, $token_endpoint)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'action' => 'revoke',
            'token' => $access_token,
        ]));
        curl_exec($ch);
    }

    /**
     * Parse a URL for read-of microformats
     * @param string $url
     */
    public function parse_read_of($url)
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
}

