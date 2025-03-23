<?php

namespace craftsnippets\mygls\models;
use craftsnippets\baseshippingplugin\BaseShipmentInfoContents;
class MyGlsShipmentInfoContents extends BaseShipmentInfoContents
{

    public $parcelShopCode;
    public $parcelShopName;
    public $test;

    public static function getJsonProperties(): array
    {
        return [
            [
                'value' => 'parcelShopCode',
                'label' => \Craft::t('shipping-toolbox', 'Parcel shop code'),
            ],
            [
                'value' => 'parcelShopName',
                'label' => \Craft::t('shipping-toolbox', 'Parcel shop name'),
            ],
        ];
    }
}