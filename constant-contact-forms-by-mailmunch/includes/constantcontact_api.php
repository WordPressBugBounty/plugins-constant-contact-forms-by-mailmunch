<?php
class ConstantcontactApi
{
    private $api_key = 'bftdvjjf64nj34q7mn3zczr8';
    private $access_token;
    private $api_endpoint = 'https://api.constantcontact.com/v2';

    /**
     * Create a new instance
     * @param string $api_key Your Constant Contact API key
     */
    public function __construct($access_token)
    {
        $this->access_token = $access_token;
    }

    public function getLists() {
        return $this->makeRequest('lists');
    }

    public function getListById($listId) {
        $lists = $this->getLists();
        foreach ($lists as $list) {
            if ($list['id'] == $listId) return $list;
        }
    }

    /**
     * Performs the underlying HTTP request.
     * @param string $method The API method to be called
     * @param int $timeout Timeout for the request in seconds
     * @return array|bool Assoc array of decoded result or false on failure
    */
    private function makeRequest($method, $timeout = 10) {
    $url = $this->api_endpoint . '/' . $method . '?api_key=' . $this->api_key;

    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->access_token,
        ),
        'user-agent' => 'PHP-MCAPI/2.0',
        'timeout' => $timeout,
        'sslverify' => $this->verify_ssl,
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
    
    }
    
}
