<?php

namespace craftsnippets\mygls\models;

use craft\base\Model;
use craft\commerce\elements\Order;
use craftsnippets\baseshippingplugin\BaseShipmentParcel;


class ShippingParcel extends BaseShipmentParcel
{
    public int $id;
    public $status;
    public array $statusObjects = [];

    public function getTrackingUrl()
    {
        return null;
    }

    public function getStatusText()
    {
        if(empty($this->statusObjects)){
            return null;
        }
        $last = end($this->statusObjects);
        return $last->statusDescription;
    }

    public function getIsDelivered(): bool
    {
        if(empty($this->statusObjects)){
            return false;
        }
        $lastStatus = end($this->statusObjects);
        return $lastStatus->getIsDelivered();
    }

}