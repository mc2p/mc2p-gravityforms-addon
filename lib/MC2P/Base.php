<?php

namespace MC2P;

require_once('Mixins.php');

/**
 * Paginator - class used on list requests
 */
class Paginator
{
    public $count;

    protected $resource;
    protected $result;

    private $previous;
    private $next;

    /**
     * @param array    $payload
     * @param string   $objectItemClass
     * @param string   $resource
     */
    public function __construct($payload, $objectItemClass, $resource)
    {
        $this->count = (isset($payload->count)) ? $payload->count : 0;
        $this->previous = (isset($payload->previous)) ? $payload->previous : NULL;
        $this->next = (isset($payload->next)) ? $payload->next : NULL;

        $this->results = array();
        $results = (isset($payload->results)) ? $payload->results : array();

        foreach ($results as $val)
        {
            $objectItem = new $objectItemClass($val, $resource);
            array_push($this->results, $objectItem);
        }

        $this->resource = $resource;
    }

    /**
     * Paginator object with the previous items
     */
    public function getPreviousList()
    {
        return (isset($this->previous))
            ? $this->resource->itemList($this->previous)
            : null;
    }

    /**
     * Paginator object with the next items
     */
    public function getNextList()
    {
        return (isset($this->next))
            ? $this->resource->itemList($this->next)
            : null;
    }
}

/**
 * Object item - class used to wrap the data from API that represent an item
 */
class ObjectItem extends ObjectItemMixin
{
    protected $mixin;
    protected $payload;
    protected $resource;

    /**
     * @param array    $payload
     * @param string   $resource
     */
    public function __construct($payload, $resource) 
    {
        $this->payload = (isset($payload)) ? $payload: array();
        $this->resource = $resource;
    }

    /**
     * Allows use the following syntax to get a field of the object: $obj->name
     *
     * @param string   $name
     */
    public function __get($name) 
    {
        switch ($name) {
            case 'payload':
                return $this->payload;

            case 'resource':
                return $this->resource;

            case '_deleted':
                return $this->_deleted;

            default:
                if (is_array($this->payload)) {
                    return $this->payload[$name];
                }
                return $this->payload->{$name};
        }
    }

    /**
     * Allows use the following syntax to get a field of the object: $obj->name = $value
     *
     * @param string   $name
     * @param string   $value
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'payload':
                $this->payload = $value;
                break;
            case 'resource':
                $this->resource = $value;
                break;
            case '_deleted':
                $this->_deleted = $value;
                break;
            default:
                if (is_array($this->payload)) {
                    $this->payload[$name] = $value;
                }
                $this->payload->{$name} = $value;
                break;
        }
    }
}

/**
 * Object item - class used to wrap the data from API that represent an item
 */
class ReadOnlyObjectItem extends ObjectItem
{
    protected $retrieveMixin;

    /**
     * @param array    $payload
     * @param string   $resource
     */
    public function __construct ($payload, $resource) 
    {
        $this->retrieveMixin = new RetrieveObjectItemMixin($payload, $resource);
        parent::__construct($payload, $resource);
    }

    /**
     * Retrieve object with object_id and return
     *
     * @param string   $objectId
     */
    public static function get ($objectId) 
    {
        $payload = array(
            $this->retrieveMixin->ID_PROPERTY => $objectId,
        );

        $obj = new self($payload, self::resource);
        $obj.retrieve();

        return $obj;
    }

    /**
     * Retrieves the data of the object item using RetrieveObjectItemMixin
     */
    public function retrieve()
    {
        $this->retrieveMixin->payload = $this->payload;
        $this->retrieveMixin->retrieve();
        $this->payload = $this->retrieveMixin->payload;
    }
}

/**
 * Object item that allows retrieve and create an item
 */
class CRObjectItem extends ReadOnlyObjectItem
{
    protected $createMixin;

    /**
     * @param array    $payload
     * @param string   $resource
     */
    public function __construct ($payload, $resource) 
    {
        $this->createMixin = new CreateObjectItemMixin($payload, $resource);
        parent::__construct($payload, $resource);
    }

    /**
     * Creates the object item with the payload data
     */
    private function __create()
    {
        $this->createMixin->payload = $this->payload;
        $this->createMixin->__create();
        $this->payload = $this->createMixin->payload;   
    }
 
    /**
     * Executes the internal function _create if the object item don't have id
     */
    public function save()
    {
        $this->createMixin->payload = $this->payload;
        $this->createMixin->save();
        $this->payload = $this->createMixin->payload;        
    }
}

/**
 * Object item that allows retrieve, create and change an item
 */
class CRUObjectItem extends CRObjectItem
{
    protected $saveMixin;

    /**
     * @param array    $payload
     * @param string   $resource
     */
    public function __construct ($payload, $resource) 
    {
        $this->saveMixin = new SaveObjectItemMixin($payload, $resource);
        parent::__construct($payload, $resource);
    }

    /**
     * Creates the object item with the payload data
     */
    private function __create()
    {
        $this->saveMixin->payload = $this->payload;
        $this->saveMixin->__create();
        $this->payload = $this->saveMixin->payload;
    }
 
    /**
     * Changes the object item with the payload data
     */
    private function __change()
    {
        $this->saveMixin->payload = $this->payload;
        $this->saveMixin->__create();
        $this->payload = $this->saveMixin->payload;   
    }

    /**
     * Executes the internal function _create if the object item don't have id
     */
    public function save()
    {
        $this->saveMixin->payload = $this->payload;
        $this->saveMixin->save();
        $this->payload = $this->saveMixin->payload;
    }
}

/**
 * Object item that allows retrieve, create and change an item
 */
class CRUDObjectItem extends CRUObjectItem
{
    protected $deleteMixin;

    /**
     * @param array    $payload
     * @param string   $resource
     */
    public function __construct ($payload, $resource) 
    {
        $this->deleteMixin = new DeleteObjectItemMixin($payload, $resource);
        parent::__construct($payload, $resource);
    }

    /**
     * Deletes the object item
     */
    public function delete()
    {
        $this->deleteMixin->payload = $this->payload;
        $this->deleteMixin->_deleted = $this->_deleted;
        $this->deleteMixin->delete();
        $this->payload = $this->deleteMixin->payload;
        $this->_deleted = $this->deleteMixin->_deleted;
    }
}

/**
 * Object item that allows retrieve, create and to get pay_url based on token of an item
 */
 class PayURLCRObjectItem extends CRObjectItem
{
    protected $payURLMixin;

    /**
     * @param array    $payload
     * @param string   $resource
     */
    public function __construct ($payload, $resource) 
    {
        $this->payURLMixin = new PayURLMixin($payload, $resource);
        parent::__construct($payload, $resource);
    }

    public function getPayUrl()
    {   
        $this->payURLMixin->payload = $this->payload;
        return $this->payURLMixin->getPayUrl();
    }
    
    public function getIframeUrl()
    {   
        $this->payURLMixin->payload = $this->payload;
        return $this->payURLMixin->getIframeUrl();
    }
}

/**
 * Resource - class used to manage the requests to the API related with a resource
 * ex: product
 */
class Resource extends ResourceMixin
{
    const PAGINATOR_CLASS = 'MC2P\Paginator';

    /**
     * @param array    $apiRequest
     */
    public function __construct ($apiRequest, $path, $objItemClass) 
    {
        parent::__construct($apiRequest, $path, $objItemClass, self::PAGINATOR_CLASS);
    }
}

/**
 * Resource that allows send requests of detail
 */
class DetailOnlyResource extends Resource
{
    protected $doResourceMixin;

    /**
     * @param array    $apiRequest
     */
    public function __construct ($apiRequest, $path, $objItemClass) 
    {
        $this->doResourceMixin = new DetailOnlyResourceMixin($apiRequest, $path, $objItemClass, self::PAGINATOR_CLASS);
        parent::__construct($apiRequest, $path, $objItemClass);
    }

    /**
     * @param string $resourceId
     * @return array Object item from server
     */
    public function detail($resourceId) 
    {
        return $this->doResourceMixin->detail($resourceId);
    }
}

/**
 * Resource that allows send requests of list and detail
 */
class ReadOnlyResource extends DetailOnlyResource
{
    protected $roResourceMixin;

    /**
     * @param array    $apiRequest
     */
    public function __construct ($apiRequest, $path, $objItemClass) 
    {
        $this->roResourceMixin = new ReadOnlyResourceMixin($apiRequest, $path, $objItemClass, self::PAGINATOR_CLASS);
        parent::__construct($apiRequest, $path, $objItemClass);
    }

    /**
     * @param array $absUrl
     * @return array Object item from server
     */
    public function itemList($absUrl) 
    {
        return $this->roResourceMixin->itemList($absUrl);
    }
}

/**
 * Resource that allows send requests of create, list and detail
 */
class CRResource extends ReadOnlyResource
{
    protected $createResourceMixin;

    /**
     * @param array    $apiRequest
     */
    public function __construct ($apiRequest, $path, $objItemClass) 
    {
        $this->createResourceMixin = new CreateResourceMixin($apiRequest, $path, $objItemClass, self::PAGINATOR_CLASS);
        parent::__construct($apiRequest, $path, $objItemClass);
    }

    /**
     * @param array $data
     * @return array Object item from server
     */
    public function create($data)
    {
        return $this->createResourceMixin->create($data);
    }
}

/**
 * Resource that allows send requests of delete, change, create, list and detail
 */
class CRUDResource extends CRResource
{
    protected $changeResourceMixin;
    protected $deleteResourceMixin;

    /**
     * @param array    $apiRequest
     */
    public function __construct ($apiRequest, $path, $objItemClass) 
    {
        $this->changeResourceMixin = new ChangeResourceMixin($apiRequest, $path, $objItemClass, self::PAGINATOR_CLASS);
        $this->deleteResourceMixin = new DeleteResourceMixin($apiRequest, $path, $objItemClass, self::PAGINATOR_CLASS);
        parent::__construct($apiRequest, $path, $objItemClass);
    }

    /**
     * @param string $resourceId
     * @param array $data
     * @return array Object item from server
     */
    public function change($resourceId, $data)
    {
        $this->changeResourceMixin->payload = $this->payload;        
        return $this->changeResourceMixin->change($resourceId, $data);
    }

    /**
     * @param string $resourceId
     * @return array Object item from server
     */
     public function delete($resourceId)
     {
         $this->deleteResourceMixin->payload = $this->payload;
         return $this->deleteResourceMixin->delete($resourceId);
     }
}
