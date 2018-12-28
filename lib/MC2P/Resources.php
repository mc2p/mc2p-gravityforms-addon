<?php

namespace MC2P;

require_once('Base.php');
require_once('Mixins.php');
require_once('Objects.php');

/**
 * Product resource
 */
class ProductResource extends CRUDResource {}

/**
 * Plan resource
 */
class PlanResource extends CRUDResource {}

/**
 * Tax resource
 */
class TaxResource extends CRUDResource {}

/**
 * Shipping resource
 */
class ShippingResource extends CRUDResource {}

/**
 * Coupon resource
 */
class CouponResource extends CRUDResource {}

/**
 * Transaction resource
 */
class TransactionResource extends CRResource {}

/**
 * Subscription resource
 */
class SubscriptionResource extends CRResource {}

/**
 * Currency resource
 */
class CurrencyResource extends ReadOnlyResource {}
 
/**
 * Gateway resource
 */
class GatewayResource extends ReadOnlyResource {}
 
/**
 * Sale resource
 */
class SaleResource extends ReadOnlyResource
{
    protected $rCVResourceMixin;
    
    /**
     * @param array    $apiRequest
     */
    public function __construct ($apiRequest, $path, $objItemClass) 
    {
        parent::__construct($apiRequest, $path, $objItemClass);
        $rCVResourceMixin = new RefundCaptureVoidResourceMixin($apiRequest, $path, $objItemClass, $this->paginatorClass);
    }
        
    /**
     * Refund the object item
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function refund(Array $data = null)
    {
        return $this->rCVResourceMixin->refund($data);
    }

    /**
     * Capture the object item
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function capture(Array $data = null)
    {
        return $this->rCVResourceMixin->capture($data);
    }

    /**
     * Void the object item
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function void(Array $data = null)
    {
        return $this->rCVResourceMixin->void($data);
    }
}
  
/**
 * PayData resource
 */
class PayDataResource extends DetailOnlyResource
{
    protected $cardShareResourceMixin;
    
    /**
     * @param array    $apiRequest
     */
    public function __construct ($apiRequest, $path, $objItemClass) 
    {
        parent::__construct($apiRequest, $path, $objItemClass);
        $cardShareResourceMixin = new CardShareResourceMixin($apiRequest, $path, $objItemClass, $this->paginatorClass);
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
        return $this->cardShareResourceMixin->card($gatewayCode, $data);
    }
     
    /**
     * Send share details
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function share(array $data = null)
    {
        return $this->cardShareResourceMixin->share($data);        
    }
}
