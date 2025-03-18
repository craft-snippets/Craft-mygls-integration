<?php

namespace craftsnippets\mygls\services;

use Craft;
use craft\commerce\Plugin as CommercePlugin;
use craft\elements\Address;
use GuzzleHttp\Client as HttpClient;
use Webapix\GLS\Client;
use Webapix\GLS\Requests\DeleteLabels;
use Webapix\GLS\Requests\GetParcelStatuses;
use Webapix\GLS\Requests\PrintLabels;
use yii\base\Component;
use craftsnippets\mygls\MyGls;
use craftsnippets\shippingtoolbox\ShippingToolbox;
use craftsnippets\mygls\models\GlsShipmentDetails;
use craftsnippets\mygls\models\ShippingParcel;
use craftsnippets\baseshippingplugin\ShippingServiceBase;
use craft\commerce\elements\Order;
use craftsnippets\shippingtoolbox\helpers\Common;
use craftsnippets\mygls\models\ParcelStatus;
use Webapix\GLS\Models\Parcel;
use Webapix\GLS\Services\ParcelShopDelivery;

class GlsService extends ShippingServiceBase
{

    public static function getPlugin()
    {
        return MyGls::getInstance();
    }

    public static function getSettings(){
        return self::getPlugin()->getSettings();
    }

    public function getApiObject()
    {
        $username = $this->getSettings()->apiUsername;
        $password = $this->getSettings()->apiPassword;
        $clientId = $this->getSettings()->apiCliendId;
        $country = $this->getSettings()->apiCountry;

        // set url
        if($this->getSettings()->testMode){
            $apiUrl = 'https://api.test.mygls';
        }else{
            $apiUrl = 'https://api.mygls';
        }
        $apiUrl = $apiUrl . '.' . $country . '/ParcelService.svc/json/';

        // account object
        $account = new \Webapix\GLS\Models\Account($apiUrl, $clientId, $username, $password);
        return $account;
    }

    public function validateAddress(?Address $address, $isDelivery)
    {
        if(is_null($address)){
            throw new \Exception(Craft::t('mygls-shipping', 'address is not set for the order'));
        }
        if(is_null($address->organization) && is_null($address->fullName)){
            throw new \Exception(Craft::t('mygls-shipping', 'organisation and full name are both empty'));
        }
        if(is_null($address->countryCode)){
            throw new \Exception(Craft::t('mygls-shipping', 'country is not set'));
        }
        if(is_null($address->postalCode)){
            throw new \Exception(Craft::t('mygls-shipping', 'postal code is not set'));
        }
        if(is_null($address->locality)){
            throw new \Exception(Craft::t('mygls-shipping', 'city is not set'));
        }
        if(is_null($address->addressLine1)){
            throw new \Exception(Craft::t('mygls-shipping', 'street is not set'));
        }
    }

    public function createShipmentDetails(Order $order, $requestSettings = [])
    {

        $class = MyGls::getShipmentDetailsClass();
        $shippingData = new $class([
            'order' => $order,
        ]);


        ////////////////////////////////////////////////////////////

        // request settings
        $defaultSettings = [
            'parcelCount' => 1,
            'senderLocationId' => null,
            'parcelDescription' => null,
            'pickupDate' => null,
            'parcelShopCode' => null,
        ];
        $requestSettings = array_merge($defaultSettings, $requestSettings);
        // empty string into null
        $requestSettings = array_map(function($value) {
            return $value === "" ? null : $value;
        }, $requestSettings);

        ////////////////////////////////////////////////////////////

        // set name
        $deliveryAddressCraft = $order->shippingAddress;
        $deliveryName = $this->getNameForAddress($deliveryAddressCraft);

        // delivery address api obj
        $deliveryAddress = new \Webapix\GLS\Models\Address(
            $deliveryName,
            $deliveryAddressCraft->countryCode,
            $deliveryAddressCraft->postalCode,
            $deliveryAddressCraft->locality,
            $deliveryAddressCraft->addressLine1,
            $deliveryAddressCraft->addressLine2,
        );
        // house number info
        if($deliveryAddressCraft->addressLine3){
            $deliveryAddress->setHouseNumberInfo($deliveryAddressCraft->addressLine3);
        }
        // email
        $deliveryAddress->setContactEmail($order->email);
        // phone
        $phoneField = ShippingToolbox::getInstance()->plugins->getPhoneField();
        if(!is_null($phoneField)){
            $deliveryAddress->setContactPhone($deliveryAddressCraft->getFieldValue($phoneField->handle));
        }
        // contact name
        if($deliveryName){
            $deliveryAddress->setContactName($deliveryName);
        }

        ////////////////////////////////////////////////////////////////////////
        // SET PICKUP ADDRESS

        $pickupAddressCraft = ShippingToolbox::getInstance()->plugins->getSenderAddress($order, $requestSettings);

        $pickupName = $this->getNameForAddress($pickupAddressCraft);
        $pickupAddress = new \Webapix\GLS\Models\Address(
            $pickupName,
            $pickupAddressCraft->countryCode,
            $pickupAddressCraft->postalCode,
            $pickupAddressCraft->locality,
            $pickupAddressCraft->addressLine1,
            $pickupAddressCraft->addressLine2,
        );
        // house number info
        if($pickupAddressCraft->addressLine3){
            $pickupAddress->setHouseNumberInfo($pickupAddressCraft->addressLine3);
        }
        // email
        $emailField = ShippingToolbox::getInstance()->plugins->getEmailField();
        if(!is_null($emailField)){
            $pickupAddress->setContactEmail($pickupAddressCraft->getFieldValue($emailField->handle));
        }

        // phone
        $phoneField = ShippingToolbox::getInstance()->plugins->getPhoneField();
        if(!is_null($phoneField)){
            $pickupAddress->setContactPhone($pickupAddressCraft->getFieldValue($phoneField->handle));
        }

        // contact name
        if($pickupAddressCraft->fullName){
            $pickupAddress->setContactName($pickupAddressCraft->fullName);
        }

        ////////////////////////////////////////////////////////////////////////
        // PARCEL OBJECT

        $account = $this->getApiObject();

        $parcel = (new Parcel())
            ->setClientNumber($account->clientNumber())
            ->setDeliveryInfo($deliveryAddress)
            ->setPickupAddress($pickupAddress)
            ->setCount($requestSettings['parcelCount'])
            ->setClientReference($order->number);

        // parcel shop
        $parcelCodeSettings = $requestSettings['parcelShopCode'];
        if($shippingData->hasParcelShopCode() || !empty($parcelCodeSettings)){
            $parcelCode = $shippingData->getParcelShopCode();
            if(!empty($parcelCodeSettings)){
                $parcelCode = $parcelCodeSettings;
            }
            $parcel->addService(
                new ParcelShopDelivery($parcelCode)
            );
        }

        // description
        if(!is_null($requestSettings['parcelDescription'])){
            $parcel->setContent($requestSettings['parcelDescription']);
        }

        // pickup date
        if(!is_null($requestSettings['pickupDate'])){
            $dateFormat = Craft::$app->getFormattingLocale()->getDateFormat('short', 'php');
            $dateObject = \DateTime::createFromFormat($dateFormat, $requestSettings['pickupDate']);
            $parcel->setPickupDate($dateObject);
        }

        // cod
        if(MyGls::getInstance()->canUseCod($order)){
            $parcel->setCodAmount(MyGls::getInstance()->getCodBeforeRequest($order));
            $parcel->setCodReference($order->number);
            if(self::getSettings()->currencyCode){
                $parcel->setCodCurrency(self::getSettings()->currencyCode);
            }
        }


////////////////////////////////////////////////////////////////////////
        // SEND REQUEST

        $client = new Client(new HttpClient);
        $request = new PrintLabels();
        $request->addParcel($parcel);

        $response = $client->on($account)->request($request);

        // api error
        if(!$response->successfull()){
            $errors = array_map(function($single){
                return $single->message();
            }, $response->errors()->all());
            $errors = join(', ', $errors);
            return [
                'success' => false,
                'error' => 'API Error: ' . $errors,
                'errorType' => 'api',
            ];
        }

        // SAVE TO ORDER - PREPARE DATA
        $requestData = $request->toArray()['ParcelList'][0];

        $shippingData->assignRequestData($requestData);

        // assign parcels
        foreach ($response->printLabelsInfo() as $single){
            $parcelObj = new ShippingParcel([
                'id' => $single->parcelId(),
                'number' => $single->parcelNumber(),
                'status' => null,
                'order' => $order,
            ]);
            $shippingData->parcels[] = $parcelObj;
        }

        // SAVE DATA
        $propertiesJson = $shippingData->encodeData();
        $plugin = MyGls::getInstance();
        $pdfContent = $response->getPdf();

        $shipmentElement = ShippingToolbox::getInstance()->plugins->saveShipmentData($propertiesJson, $order, $plugin, $pdfContent);

        return [
            'success' => true,
            'shipment' => $shipmentElement,
        ];
    }

    public function removeShipmentDetails(Order $order, $shipmentDetails)
    {
        $account = $this->getApiObject();
        $client = new Client(new HttpClient);
        $request = new DeleteLabels();

        foreach ($shipmentDetails->parcels as $parcel){
            $parcelId = $parcel->id;
            $request->addParcelId($parcelId);
        }
        $response = $client->on($account)->request($request);

        if(!$response->successfull()){
            $errors = array_map(function($single){
                return $single->message();
            }, $response->errors()->all());
            $errors = join(', ', $errors);
            return [
                'success' => false,
                'error' => 'API Error: ' . $errors,
                'errorType' => 'api',
            ];
        }

        return [
            'success' => true,
        ];
    }

    public function updateParcelsStatus($order, $shipmentDetails)
    {
        $parcels = [];
        foreach ($shipmentDetails->parcels as $parcel){
            $result = $this->getParcelStatus($parcel->number);
            if($result['success']){
                $parcel->statusObjects = $result['result'];
                $parcels[] = $parcel;
            }else{
                return [
                    'success' => false,
                    'error' => $result['error'],
                    'errorType' => $result['errorType'],
                ];
            }
        }

        $shipmentDetails->parcels = $parcels;
        $json = $shipmentDetails->encodeData();
        $result = [
            'success' => true,
            'json' => $json,
            'parcels' => $parcels,
        ];
        return $result;
    }

    public function getParcelStatus($parcelNumber)
    {
        $account = $this->getApiObject();
        $client = new Client(new HttpClient);
        $request = new GetParcelStatuses($parcelNumber);
        $response = $client->on($account)->request($request);

        // api error
        if(!$response->successfull()){
            $errors = array_map(function($single){
                return $single->message();
            }, $response->errors()->all());
            $errors = join(', ', $errors);
            return [
                'success' => false,
                'error' => $parcelNumber . ' - API Error: ' . $errors,
                'errorType' => 'api',
            ];
        }
        $parcelStatusList = $response->ParcelStatusList();

        $statusObjects = array_map(function($single){
            return new ParcelStatus([
                'depotCity' => $single->depotCity(),
                'depotNumber' => $single->depotNumber(),
                'statusCode' => $single->statusCode(),
                'statusDate' => $single->statusDate(),
                'statusDescription' => $single->statusDescription(),
                'statusInfo' => $single->statusInfo(),
            ]);
        }, $parcelStatusList);
        return [
            'success' => true,
            'result' => $statusObjects,
        ];
    }

}
