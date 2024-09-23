<?php
namespace Ctct\Util;

use Ctct\Exceptions\CtctException;

/**
 * Wrapper for curl HTTP request
 *
 * @package Util
 * @author Constant Contact
 */
class RestClient implements RestClientInterface
{
    /**
     * Make an Http GET request
     * @param $url - request url
     * @param array $headers - array of all http headers to send
     * @return WP_Error|array - The response body, http info, and error (if one exists)
     */
    public function get($url, array $headers)
    {
        return self::httpRequest($url, "GET", $headers);
    }

    /**
     * Make an Http POST request
     * @param $url - request url
     * @param array $headers - array of all http headers to send
     * @param $data - data to send with request
     * @return WP_Error|array - The response body, http info, and error (if one exists)
     */
    public function post($url, array $headers = array(), $data = null)
    {
        return self::httpRequest($url, "POST", $headers, $data);
    }

    /**
     * Make an Http PUT request
     * @param $url - request url
     * @param array $headers - array of all http headers to send
     * @param $data - data to send with request
     * @return WP_Error|array - The response body, http info, and error (if one exists)
     */
    public function put($url, array $headers = array(), $data = null)
    {
        return self::httpRequest($url, "PUT", $headers, $data);
    }

    /**
     * Make an Http DELETE request
     * @param $url - request url
     * @param array $headers - array of all http headers to send
     * @return WP_Error|array - The response body, http info, and error (if one exists)
     */
    public function delete($url, array $headers = array())
    {
        return self::httpRequest($url, "DELETE", $headers);
    }

    /**
     * Make an HTTP request
     * @param $url - request url
     * @param $method - HTTP method to use for the request
     * @param array $headers - any http headers that should be included with the request
     * @param string|null $data - payload to send with the request, if any
     * @return WP_Error|array
     */
    private static function httpRequest($url, $method, array $headers = array(), $data = null)
    {
        //adding the version header to the existing headers
        $headers[] = self::getVersionHeader();

        $args = array(
            'headers' => $headers,
            'user-agent' => "ConstantContact AppConnect PHP Library v" . Config::get('settings.version'),
            'sslverify' => false,
            'method' => $method,
        );

        // add data to send with request if present
        if ($data) {
            $args['body'] = $data;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new CtctException($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $body_decoded = json_decode($body, true);

        if (isset($body_decoded[0]) && array_key_exists('error_key', $body_decoded[0])) {
            $ex = new CtctException($body);
            $ex->setErrors($body_decoded);
            throw $ex;
        }

        return array(
            'body' => $body,
            'info' => wp_remote_retrieve_headers($response),
            'error' => null,
        );
    }

    /**
     * Returns the version header for the rest calls
     * @return string
     */
    public static function getVersionHeader()
    {
        return 'x-ctct-request-source: sdk.php.' . Config::get('settings.version');
    }
}