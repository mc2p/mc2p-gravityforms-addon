<?php

namespace MC2P;

require_once('Base.php');
require_once('Mixins.php');

/**
 * Product object
 */
class Product extends CRUDObjectItem {}

/**
 * Plan object
 */
class Plan extends CRUDObjectItem {}
    
/**
 * Tax object
 */
class Tax extends CRUDObjectItem {}
    
/**
 * Shipping object
 */
class Shipping extends CRUDObjectItem {}

/**
 * Coupon object
 */
class Coupon extends CRUDObjectItem {}

/**
 * Transaction object
 */
class Transaction extends PayURLCRObjectItem {}

/**
 * Subscription object
 */
class Subscription extends PayURLCRObjectItem {}

/**
 * Currency object
 */
class Currency extends ReadOnlyObjectItem {}

/**
 * Gateway object
 */
class Gateway extends ReadOnlyObjectItem {}

/**
 * Sale object
 */
class Sale extends ReadOnlyObjectItem 
{
    protected $rCVMixin;
    
    /**
     * @param array    $payload
     * @param string   $resource
     */
    public function __construct ($payload, $resource) 
    {
        $this->rCVMixin = new RefundCaptureVoidObjectItemMixin($payload, $resource);
        parent::__construct($payload, $resource);
    }
    
    /**
     * Refund the object item
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function refund(Array $data = null)
    {
        $this->rCVMixin->payload = $this->payload;
        return $this->rCVMixin->refund($data);
    }
 
    /**
     * Capture the object item
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function capture(Array $data = null)
    {
        $this->rCVMixin->payload = $this->payload;        
        return $this->rCVMixin->capture($data);
    }
 
    /**
     * Void the object item
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function void(Array $data = null)
    {
        $this->rCVMixin->payload = $this->payload;        
        return $this->rCVMixin->void($data);
    }
}

/**
 * PayData object
 */
class PayData extends ReadOnlyObjectItem 
{
    const ID_PROPERTY = 'token';

    protected $cardShareMixin;

    /**
     * @param array    $payload
     * @param string   $resource
     */
    public function __construct ($payload, $resource) 
    {
        $this->cardShareMixin = new CardShareObjectItemMixin();
        parent::__construct($payload, $resource);
    }
    
    /**
     * Send card details
     * 
     * @param array $gatewayCode
     * @param array $data
     * @return array Object item from server
     */
    public function card(string $gatewayCode, array $data = null)
    {
        $this->cardShareMixin->payload = $this->payload;        
        return $this->cardShareMixin->card($gatewayCode, $data);
    }
     
    /**
     * Send share details
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function share(array $data = null)
    {
        $this->cardShareMixin->payload = $this->payload;        
        return $this->cardShareMixin->share($data);        
    }
}
