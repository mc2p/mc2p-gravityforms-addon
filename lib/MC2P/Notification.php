<?php

namespace MC2P;

/**
 * Class to manage notification from MyChoice2Pay
 */
class NotificationData 
{
    protected $payload;        // Content of request from MyChoice2Pay         
    protected $mc2p;           // MC2PClient

    /**
     * @param string $payload
     * @param string $mc2p
     */
    public function __construct($payload, $mc2p) 
    {
        $this->payload = $payload;
        $this->mc2p = $mc2p;        
    }

    /**
     * @return string Status of payment
     */
    public function getId() 
    {
        return $this->payload['id'];
    }

    /**
     * @return string Status of payment
     */
    public function getStatus() 
    {
        return $this->payload['status'];
    }

    /**
     * @return string Status of subscription
     */
    public function getSubscriptionStatus() 
    {
        return $this->payload['subscription_status'];
    }

    /**
     * @return string Type of payment
     */
    public function getType() 
    {
        return $this->payload['type'];
    }

    /**
     * @return string OrderId sent when payment was created
     */
    public function getOrderId() 
    {
        return $this->payload['order_id'];
    }

    /**
     * @return string Action executed
     */
    public function getAction() 
    {
        return $this->payload['action'];
    }

    /**
     * @return string Action of sale executed
     */
    public function getSaleAction() 
    {
        return $this->payload['sale_action'];
    }

    /**
     * @return string Transaction generated when payment was created
     */
    public function getTransaction() 
    {
        $t = $this->getType(); 

        if ($t != 'P') 
        {
            return null;
        }

        $id = $this->getId();
        if (isset($id)) {
            $transaction = $this->mc2p->Transaction(
                array(
                    "id" => $id
                )
            );
            $transaction->retrieve();
            return $transaction;
        }
        return null;
    }

    /**
     * @return string Subscription generated when payment was created
     */
    public function getSubscription() 
    {
        $t = $this->getType(); 

        if ($t != 'S') 
        {
            return null;
        }

        $id = $this->getId();
        if (isset($id)) {
            $subscription = $this->mc2p->Subscription(
                array(
                    "id" => $id
                )
            );
            $subscription->retrieve();
            return $subscription;
        }
        return null;
    }

    /**
     * @return string Sale generated when payment was paid
     */
    public function getSale() 
    {
        if (isset($this->payload['sale_id'])) {
            $sale = $this->mc2p->Sale(
                array(
                    "id" => $this->payload['sale_id']
                )
            );
            $sale->retrieve();
            return $sale;
        }
        return null;
    }
}
