<?php

namespace craftsnippets\mygls\models;

use Craft;
use craft\base\Model;
use craft\commerce\Plugin as CommercePlugin;

class Settings extends Model
{
    const COD_ENABLED = 'enabled';
    const COD_DISABLED = 'disabled';

    const PARCEL_SHOP_ENABLED = 'enabled';
    const PARCEL_SHOP_DISABLED = 'disabled';

    public $apiUsername;
    public $apiPassword;
    public $apiCliendId;
    public $apiCountry;
    public bool $testMode = false;
    public array $enabledShippingMethods = [];
    public $currencyCode;
    public $parcelShopWidget = self::PARCEL_WIDGET_MAP;

    public function attributeLabels()
    {
        return [
            'apiUsername' => Craft::t('mygls-shipping', 'API username'),
            'apiPassword' => Craft::t('mygls-shipping', 'API password'),
            'apiCliendId' => Craft::t('mygls-shipping', 'API client id'),
            'apiCountry' => Craft::t('mygls-shipping', 'API country'),
            'testMode' => Craft::t('mygls-shipping', 'API test mode'),
            'enabledShippingMethods' => Craft::t('mygls-shipping', 'Shipping methods with MyGls integration enabled'),
            'currencyCode' => Craft::t('mygls-shipping', 'Currency code for cash on delivery parcels'),
            'parcelShopWidget' => Craft::t('mygls-shipping', 'Parcel delivery shop widget'),
        ];
    }

    public function getCountryOptions()
    {
        return [
            [
                'label' => Craft::t('mygls-shipping', 'Select'),
                'value' => null,
            ],
            [
                'label' => Craft::t('mygls-shipping', 'Croatia'),
                'value' => 'hr',
            ],
            [
                'label' => Craft::t('mygls-shipping', 'Czechia'),
                'value' => 'cz',
            ],
            [
                'label' => Craft::t('mygls-shipping', 'Hungary'),
                'value' => 'hu',
            ],
            [
                'label' => Craft::t('mygls-shipping', 'Romania'),
                'value' => 'ro',
            ],
            [
                'label' => Craft::t('mygls-shipping', 'Slovenia'),
                'value' => 'si',
            ],
            [
                'label' => Craft::t('mygls-shipping', 'Slovakia'),
                'value' => 'sk',
            ],
            [
                'label' => Craft::t('mygls-shipping', 'Serbia'),
                'value' => 'rs',
            ],

        ];
    }

    public function getShippingMethodsColumns()
    {
        $shippingMethods = CommercePlugin::getInstance()->getShippingMethods()->getAllShippingMethods();
        $shippingMethodsOptions = $shippingMethods->map(function ($shippingMethod) {
            return [
                'label' => $shippingMethod->name,
                'value' => $shippingMethod->id,
            ];
        });
        $codOptions = [
            [
                'label' => Craft::t('mygls-shipping', 'Disabled'),
                'value' => self::COD_DISABLED,
            ],
            [
                'label' => Craft::t('mygls-shipping', 'Enabled'),
                'value' => self::COD_ENABLED,
            ],
        ];
        $parcelShopOptions = [
            [
                'label' => Craft::t('mygls-shipping', 'Disabled'),
                'value' => self::PARCEL_SHOP_DISABLED,
            ],
            [
                'label' => Craft::t('mygls-shipping', 'Enabled'),
                'value' => self::PARCEL_SHOP_ENABLED,
            ],
        ];
        $columns = [
            'shippingMethodId' => [
                'heading' => Craft::t('mygls-shipping', 'Shipping method'),
                'type' => 'select',
                'options' => $shippingMethodsOptions,

            ],
            'cod' => [
                'heading' => Craft::t('mygls-shipping', 'Cash on delivery'),
                'type' => 'select',
                'options' => $codOptions,
            ],
            'parcelShop' => [
                'heading' => Craft::t('mygls-shipping', 'Parcel shop'),
                'type' => 'select',
                'options' => $parcelShopOptions,
            ],
        ];
        return $columns;
    }

    const PARCEL_WIDGET_SELECT = 'select';
    const PARCEL_WIDGET_MAP = 'map';

    public function getParcelShopWidgetOptions()
    {
        return [
            [
                'value' => self::PARCEL_WIDGET_MAP,
                'label' => Craft::t('mygls-shipping', 'Locations map'),
            ],
            [
                'value' => self::PARCEL_WIDGET_SELECT,
                'label' => Craft::t('mygls-shipping', 'Locations list'),
            ],
        ];
    }

}