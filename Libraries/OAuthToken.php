<?php

namespace KongaPay;

if (!function_exists('curl_init')) {
    throw new \Exception('The KongaPay KPayAPI class is unable to find the CURL PHP extension.');
}

if (!function_exists('json_decode')) {
    throw new \Exception('The KongaPay KPayAPI class is unable to find the JSON PHP extension.');
}

if (!function_exists('is_json')) {
    function is_json($json)
    {
        json_decode($json);

        return (json_last_error() == JSON_ERROR_NONE);
    }
}

class OAuthToken
{
    public $result;
    public $error;

    protected $merchant_id;
    protected $oauth_access_token;
    protected $refresh_token;
    protected $is_test;

    private $version = '1.0.0';
    private $oauth_url;
    private $oauth_client_secret;

    /**
     * KongaPayOAuth constructor.
     * @param $merchant_id
     * @param $oauth_client_secret
     * @param $is_test
     */
    public function __construct($merchant_id, $oauth_client_secret, $is_test = true)
    {
        $this->is_test = $is_test;
        $this->oauth_url = $is_test ? 'https://staging-auth.kongapay.com' : 'https://auth.kongapay.com';
        $this->oauth_merchant_id = trim($merchant_id);
        $this->merchant_id = trim($merchant_id);
        $this->oauth_client_secret = trim($oauth_client_secret);
        $this->oauth_access_token = FALSE;
        $this->refresh_token = FALSE;
        $this->error = FALSE;
    }

    /**
     * Refresh Access Token using Refresh token
     *
     * @param $refreshToken
     * @return mixed
     */
    public function refreshAccessToken($refreshToken)
    {
        $data = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->oauth_merchant_id,
            'client_secret' => $this->oauth_client_secret
        );

        $resource = '/token';

        $this->connect($this->oauth_url . $resource, 'POST', json_encode($data));

        $response = (is_object($this->result) && isset($this->result->data) && isset($this->result->data->authorize)) ? $this->result->data->authorize : NULL;

        if ($response) {
            $this->setAccessToken($response->access_token);
        }

        return $response;

    }

    /**
     * Performs an API call
     *
     * @param $url
     * @param $method
     * @param string $params
     * @param bool $decode
     */
    protected function connect($url, $method, $params = '', $decode = TRUE)
    {
        $http_status = NULL;
        $this->result = NULL;
        $this->error = FALSE;

        $fields = '';

        $headers = array();
        $headers['Content-Type'] = 'Content-Type: application/json';
        $headers['Accept'] = 'Accept: application/json';

        if (($method == 'POST' || $method == 'PUT' || $method == 'DELETE') && !empty($params)) {
            $fields = (is_array($params)) ? http_build_query($params) : $params;
            $headers['Content-Length'] = 'Content-Length: ' . strlen($fields);
        }

        $opts = array(
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'KongaPay-OAuth-' . $this->version,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers
        );

        if (($method == 'POST' || $method == 'PUT' || $method == 'DELETE') && !empty($params)) {
            $opts[CURLOPT_POSTFIELDS] = $fields;
        }

        if ($method == 'POST' && is_array($params)) {
            $opts[CURLOPT_POST] = count($params);
        } elseif ($method == 'PUT') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        } elseif ($method == 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        } elseif ($method == 'POST') {
            $opts[CURLOPT_POST] = TRUE;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_status != 200) {
            $this->error = TRUE;
        }

        $this->result = (($decode === TRUE) && (is_json($result) === TRUE)) ? json_decode($result) : $result;
        curl_close($ch);
    }

    /**
     * @param $accessToken
     */
    public function setAccessToken($accessToken)
    {
        // Todo: Cache Access Token for 6 hours
        $this->oauth_access_token = $accessToken;
    }

    /**
     * @return bool | string
     */
    public function getAccessToken()
    {
        //Todo: Get Access Token from Cache first
        if (!$this->oauth_access_token) {
            $this->oauth_access_token = $this->generateAccessToken();
        }

        return $this->oauth_access_token;
    }

    /**
     * @return bool | string
     */
    private function generateAccessToken()
    {
        $accessCode = $this->fetchAccessCode();

        if ($accessCode) {
            $response = $this->fetchAccessTokenByAccessCode($accessCode);

            if ($response) {
                $this->setAccessToken($response->access_token);
                $this->setRefreshToken($response->refresh_token);

                return $response->access_token;
            }
        }

        return false;
    }

    /**
     * Fetches OAuth2 Access Code
     * @return mixed
     */
    private function fetchAccessCode()
    {
        $resource = '/authorize?response_type=code&client_id=' . $this->oauth_merchant_id . '&state=' . urlencode(microtime());

        $this->connect($this->oauth_url . $resource, 'GET');

        return (is_object($this->result) && isset($this->result->data) && isset($this->result->data->code)) ? $this->result->data->code : NULL;
    }

    /**
     * Fetches OAuth2 Access Token from the OAuth2 Access Code
     * @param $accessCode
     * @return mixed
     */
    private function fetchAccessTokenByAccessCode($accessCode)
    {
        $data = array(
            'grant_type' => 'authorization_code',
            'code' => $accessCode,
            'client_id' => $this->oauth_merchant_id,
            'client_secret' => $this->oauth_client_secret
        );

        $resource = '/token';

        $this->connect($this->oauth_url . $resource, 'POST', json_encode($data));

        return (is_object($this->result) && isset($this->result->data) && isset($this->result->data->authorize)) ? $this->result->data->authorize : NULL;
    }

    /**
     * @return bool | string
     */
    public function getRefreshToken()
    {
        // Todo: Get Refresh Token from Cache
        return $this->refresh_token;
    }

    /**
     * @param $accessToken
     */
    public function setRefreshToken($accessToken)
    {
        // Todo: Cache Refresh Token for 14 days.
        $this->refresh_token = $accessToken;
    }
}
