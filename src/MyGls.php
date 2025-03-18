<?php

namespace craftsnippets\mygls;

use Craft;
use craft\commerce\elements\Order;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\ArrayHelper;
use craft\web\View;
use craftsnippets\baseshippingplugin\ShippingPlugin;
use craftsnippets\baseshippingplugin\ShippingServiceBase;
use craftsnippets\mygls\models\Settings;
use craftsnippets\mygls\models\GlsShipmentDetails;
use craftsnippets\mygls\services\GlsService;
use craftsnippets\mygls\elements\actions\CreateParcelsAction;
use yii\base\Event;

class MyGls extends ShippingPlugin
{
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots[$this->handle] = __DIR__ . '/templates';
            }
        );
    }

    public static function config(): array
    {
        return [
            'components' => ['gls' => GlsService::class],
        ];
    }

    ///////////////////////

    public static function getSettingsTemplate(): string
    {
        return 'mygls-shipping/settings.twig';
    }

    public static function getSettingsClass(): string
    {
        return Settings::class;
    }

    public static function getShipmentDetailsClass(): string
    {
        return GlsShipmentDetails::class;
    }

    public static function getShippingName(): string
    {
        return 'MyGLS';
    }

    public function isAllowedForOrder(Order $order): bool
    {
        $enabledTable = $this->getSettings()->enabledShippingMethods;
        $enabledIds = array_column($enabledTable, 'shippingMethodId');

        $orderShippingMethod = $order->shippingMethod;
        if(is_null($orderShippingMethod)){
            return false;
        }

        if(in_array($orderShippingMethod->id, $enabledIds)){
            return true;
        }
        return false;
    }

    public function parcelShopAllowedForOrder(Order $order)
    {
        $enabledTable = $this->getSettings()->enabledShippingMethods;
        $shippingMethod = $order->shippingMethod;
        if(is_null($shippingMethod)){
            return false;
        }

        // only uses firt occurance of this shipping method
        $allowed = false;
        $enabledTable = $this->getSettings()->enabledShippingMethods;
        foreach ($enabledTable as $row){
            if(isset($row['shippingMethodId']) && $row['shippingMethodId'] == $shippingMethod->id && isset($row['parcelShop']) && $row['parcelShop'] == $this->getSettings()::PARCEL_SHOP_ENABLED){
                $allowed = true;
                break;
            }
        }
        return $allowed;
    }

    public function getSettingsErrors()
    {
        $errors = [];

        $required = [
            'apiUsername',
            'apiPassword',
            'apiCliendId',
            'apiCountry',
        ];

        foreach ($required as $single){
            if(empty($this->getSettings()->{$single})){
                $label = $this->getSettings()->attributeLabels()[$single] ?? null;
                $errors[] = $label . ' is required.';
            }
        }

        return $errors;
    }

    public function canUseCod(Order $order): bool
    {
//        $methodsIds = array_column($this->getSettings()->enabledShippingMethods, 'shippingMethodId');
//        if(is_null($order->shippingMethod) || !in_array($order->shippingMethod->id, $methodsIds)){
//            return false;
//        }
//        $shippingMetgodOption = array_filter($this->getSettings()->enabledShippingMethods, function($single) use ($order){
//            return $single['shippingMethodId'] == $order->shippingMethod->id;
//        });
//        $shippingMetgodOption = reset($shippingMetgodOption);
//        if(($shippingMetgodOption['cod'] ?? false) == $this->getSettings()::COD_ENABLED){
//            return true;
//        }
        return false;
    }

    public static function getLabelFolderName(): string
    {
        return 'my-gls';
    }

    public function getPluginService(): ShippingServiceBase
    {
        return $this->gls;
    }

    public function getCreateParcelsActionClass()
    {
        return CreateParcelsAction::class;
    }

    public function senderAddressRequired()
    {
        return true;
    }

    public function useInputPickupDate()
    {
        return true;
    }

    // updating immediately causes API error
    public function updateImmediatelyAfterCreation()
    {
        return false;
    }

    public function supportsParcelShops()
    {
        return true;
    }

    public function getParcelShopsParametersErrors()
    {
        return Craft::t('mygls-shipping', 'PSD service requires delivery address to have name/organisation name, contact phone and contact email.');
    }

    public function getParcelShopSelectWidgetTemplate(): ?string
    {
        return 'mygls-shipping/parcel-shop-select.twig';
    }

}
