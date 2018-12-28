<?php

namespace MC2P;

require_once('Request.php');
require_once('Objects.php');
require_once('Resources.php');
require_once('Notification.php');

/**
 * MC2P - class used to manage the communication with MyChoice2Pay API
 */
class MC2PClient
{
    protected $apiRequest;

    /**
     * @param string   $key
     * @param string   $secret
     */
    public function __construct($key, $secret) 
    {
        $this->apiRequest = new APIRequest($key, $secret);

        $this->product = new ProductResource($this->apiRequest, '/product/', 'MC2P\Product');
        $this->plan = new PlanResource($this->apiRequest, '/plan/', 'MC2P\Plan');
        $this->tax = new TaxResource($this->apiRequest, '/tax/', 'MC2P\Tax');
        $this->shipping = new ShippingResource($this->apiRequest, '/shipping/', 'MC2P\Shipping');
        $this->coupon = new CouponResource($this->apiRequest, '/coupon/', 'MC2P\Coupon');
        $this->transaction = new TransactionResource($this->apiRequest, '/transaction/', 'MC2P\Transaction');
        $this->subscription = new SubscriptionResource($this->apiRequest, '/subscription/', 'MC2P\Subscription');
        $this->currency = new CurrencyResource($this->apiRequest, '/currency/', 'MC2P\Currency');
        $this->gateway = new GatewayResource($this->apiRequest, '/gateway/', 'MC2P\Gateway');
        $this->payData = new PayDataResource($this->apiRequest, '/pay/', 'MC2P\PayData');
        $this->sale = new SaleResource($this->apiRequest, '/sale/', 'MC2P\Sale');
    }

    /**
     * @param string    $class; Object class name
     * @param object    $resource; Resource instance
     * @param array     $payload
     */
    private function __wrapper ($class, $resource, $payload) 
    {
        return new $class ($payload, $resource, $payload);
    }

    /**
     * @param array   $payload
     */
    public function Product ($payload = array()) 
    {
        return $this->__wrapper('MC2P\Product', $this->product, $payload);
    }

    /**
     * @param array   $payload
     */
    public function Plan ($payload = array()) 
    {
        return $this->__wrapper('MC2P\Plan', $this->plan, $payload);
    }

    /**
     * @param array   $payload
     */
    public function Tax ($payload = array()) 
    {
        return $this->__wrapper('MC2P\Tax', $this->tax, $payload);
    }

    /**
     * @param array   $payload
     */
    public function Shipping ($payload = array()) 
    {
        return $this->__wrapper('MC2P\Shipping', $this->shipping, $payload);
    }

    /**
     * @param array   $payload
     */
    public function Coupon ($payload = array()) 
    {
        return $this->__wrapper('MC2P\Coupon', $this->coupon, $payload);
    }

    /**
     * @param array   $payload
     */
    public function Transaction ($payload = array()) 
    {   
        return $this->__wrapper('MC2P\Transaction', $this->transaction, $payload);
    }

    /**
     * @param array   $payload
     */
    public function Subscription ($payload = array()) 
    {
        return $this->__wrapper('MC2P\Subscription', $this->subscription, $payload);
    }

    /**
     * @param array   $payload
     */
    public function Sale ($payload = array()) 
    {
        return $this->__wrapper('MC2P\Sale', $this->sale, $payload);
    }

    /**
     * @param array   $payload
     */
    public function Currency ($payload = array()) 
    {
        return $this->__wrapper('MC2P\Currency', $this->currency, $payload);
    }

    /**
     * @param array   $payload
     */
    public function Gateway ($payload = array()) 
    {
        return $this->__wrapper('MC2P\Gateway', $this->gateway, $payload);
    }

    /**
     * @param array   $payload
     */
    public function PayData ($payload = array()) 
    {
        return $this->__wrapper('MC2P\PayData', $this->payData, $payload);
    }

    /**
     * @param array   $payload
     */
    public function NotificationData ($payload = array()) 
    {
        return $this->__wrapper('MC2P\NotificationData', $this, $payload);
    }
}
