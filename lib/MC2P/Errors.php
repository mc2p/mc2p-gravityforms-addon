<?php

namespace MC2P;

use Exception;

/**
 * MC2P Error - class used to manage the exceptions related with mc2p library
 */
class MC2PError extends Exception
{
    protected $jsonBody;                // Response from server
    protected $resource;                // Class resource used when the error raised
    protected $resourceId;              // Resource id requested when the error raised

    /**
     * @param string    $message
     * @param string    $jsonBody
     * @param array     $resource
     * @param string    $resourceId
     */
    public function __construct($message, $jsonBody = null, $resource = null, $resourceId = null) 
    {
        $this->jsonBody = $jsonBody;
        $this->resource = $resource;        
        $this->resourceId = $resourceId;        
        
        parent::__construct($message);
    }

    /**
     * @return string Error type and response
     */
    public function __toString() 
    {
        return "{$this->message}";
    }
}


class InvalidRequestMC2PError extends MC2PError {}


class BadUseMC2PError extends MC2PError {}
