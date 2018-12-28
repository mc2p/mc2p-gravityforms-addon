<?php

namespace MC2P;

require_once('Errors.php');

abstract class Method 
{
    const GET = 'GET';
    const POST = 'POST';
    const PATCH = 'PATCH';
    const DELETE = 'DELETE';
}


/**
 * Class used to connect with the API
 */
class APIRequest
{
    protected $key;             // Response from server
    protected $secret;          // Class resource used when the error raised

    const AUTHORIZATION_HEADER  = 'AppKeys';
    CONST API_URL               = 'api.mychoice2pay.com/v1';

    /**
     * @param string   $key
     * @param string    $secret
     */
    public function __construct($key, $secret) 
    {
        $this->key = $key;
        $this->secret = $secret;        
    }

    /**
     * The absolute url to send the request
     * 
     * @param string    $path
     *
     * @return String
     */
    private function __getAbsURL ($path)
    {
        return "https://".self::API_URL."{$path}";
    }

    /**
     * Creates the headers to include in the request
     * 
     * @return Array
     */
    private function __getHeaders ()
    {
        $header = self::AUTHORIZATION_HEADER;
        return array(
            "authorization: {$header} {$this->key}:{$this->secret}",
            "content-type: application/json",
        );
    }

    /**
     * @param string    $method
     * @param string    $status
     * @param string    $path
     * @param           $data
     * @param string    $absUrl
     * @param           $resource
     * @param string    $resourceId
     *
     * @return Array
     */
    private function __request ($method, $status, $path = null, $data = null, $absUrl = null, $resource = null, $resourceId = null) 
    {
        $url = ($absUrl !== null) ? $absUrl : $this->__getAbsURL($path);
        $headers = $this->__getHeaders();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);                                                           
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 

        if ($data !== null) {
            $payload = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);  
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch); 
        
        $result = json_decode($response);

        if ($httpCode != $status)
        { 
            throw new InvalidRequestMC2PError("Error {$httpCode}", $result, $resource, $resourceId);
        } 
        elseif ($httpCode === 204) 
        {
            return array();
        }
        
        return $result; 
    }

    /**
     * @param string    $path
     * @param           $data
     * @param string    $absUrl
     * @param           $resource
     * @param string    $resourceId
     *
     * @return Array
     */
    public function get ($path = null, $data = null, $absUrl = null, $resource = null, $resourceId = null)
    {
        return $this->__request(Method::GET, 200, $path, $data, $absUrl, $resource, $resourceId);
    }

    /**
     * @param string    $path
     * @param           $data
     * @param string    $absUrl
     * @param           $resource
     * @param string    $resourceId
     *
     * @return Array
     */
    public function patch ($path = null, $data = null, $absUrl = null, $resource = null, $resourceId = null)
    {
        return $this->__request(Method::PATCH, 200, $path, $data, $absUrl, $resource, $resourceId);
    }

    /**
     * @param string    $path
     * @param           $data
     * @param string    $absUrl
     * @param           $resource
     * @param string    $resourceId
     *
     * @return Array
     */
    public function post ($path = null, $data = null, $absUrl = null, $resource = null, $resourceId = null)
    {
        return $this->__request(Method::POST, 201, $path, $data, $absUrl, $resource, $resourceId);
    }

    /**
     * @param string    $path
     * @param           $data
     * @param string    $absUrl
     * @param           $resource
     * @param string    $resourceId
     *
     * @return Array
     */
    public function post200 ($path = null, $data = null, $absUrl = null, $resource = null, $resourceId = null)
    {
        return $this->__request(Method::POST, 200, $path, $data, $absUrl, $resource, $resourceId);
    }

    /**
     * @param string    $path
     * @param           $data
     * @param string    $absUrl
     * @param           $resource
     * @param string    $resourceId
     *
     * @return Array
     */
    public function delete ($path = null, $data = null, $absUrl = null, $resource = null, $resourceId = null)
    {
        return $this->__request(Method::DELETE, 204, $path, $data, $absUrl, $resource, $resourceId);
    }
}