<?php

namespace craftsnippets\mygls\models;

use Craft;


use craft\commerce\elements\Order;
use craftsnippets\baseshippingplugin\BaseShippingDetails;
use craftsnippets\mygls\models\ParcelStatus;
use craftsnippets\shippingtoolbox\ShippingToolbox;

class GlsShipmentDetails extends BaseShippingDetails
{

    // address

    public $ClientNumber;
    public $ClientReference;
    public $CODAmount;
    public $CODCurrency;
    public $CODReference;
    public $Content;
    public $Count;
    public $DeliveryAddress;
    public $PickupAddress;
    public $PickupDate;
    public $ServiceList;
    public $PSDParameter;

    public function init(): void
    {
        // decode from field value only if json was provided
        if(is_null($this->jsonData)){
            return;
        }
        $data = json_decode($this->jsonData, true);

        // todo
        // assign parcels
        if(isset($data['parcels']) && is_array($data['parcels'])){
            $parcels = [];
            foreach ($data['parcels'] as $parcelInArray) {
                if(!isset($parcelInArray['number'])){
                    continue;
                }
                $statusObjects = array_map(function($status){
                    return new ParcelStatus(
                        [
                            'depotCity' => $status['depotCity'] ?? null,
                            'depotNumber' => $status['depotNumber'] ?? null,
                            'statusCode' => $status['statusCode'] ?? null,
                            'statusDate' => $status['statusDate'] ?? null,
                            'statusDescription' => $status['statusDescription'] ?? null,
                            'statusInfo' => $status['statusInfo'] ?? null,
                        ]
                    );
                }, $parcelInArray['status'] ?? []);
                $parcel = new ShippingParcel(
                    [
                        'number' => $parcelInArray['number'] ?? null,
                        'id' => $parcelInArray['id'] ?? null,
                        'status' => $parcelInArray['status'] ?? null,
                        'order' => $this->order,
                        'statusObjects' => $statusObjects,
                    ]
                );
                $parcels[] = $parcel;
            }
            $this->parcels = $parcels;
        }

        // assign json properties
        foreach ($this->getJsonProperties() as $single) {
            $property = $single['value'];
            if(isset($data[$property])){
                $this->{$property} = $data[$property];
            }
        }
    }

    public static function getJsonProperties(): array
    {
        return [
            [
                'value' => 'ClientNumber',
                'label' => \Craft::t('shipping-toolbox', 'Client number'),
            ],
            [
                'value' => 'ClientReference',
                'label' => \Craft::t('shipping-toolbox', 'Parcel reference'),
            ],
            [
                'value' => 'CODAmount',
                'label' => \Craft::t('shipping-toolbox', 'Cash on delivery amount'),
            ],
            [
                'value' => 'CODCurrency',
                'label' => \Craft::t('shipping-toolbox', 'Cash on delivery currency'),
            ],
            [
                'value' => 'CODReference',
                'label' => \Craft::t('shipping-toolbox', 'Cash on delivery reference'),
            ],
            [
                'value' => 'Content',
                'label' => \Craft::t('shipping-toolbox', 'Parcel info printed on label'),
            ],
            [
                'value' => 'Count',
                'label' => \Craft::t('shipping-toolbox', 'Count of parcels'),
            ],
            [
                'value' => 'DeliveryAddress',
                'label' => \Craft::t('shipping-toolbox', 'Delivery address'),
            ],
            [
                'value' => 'PickupAddress',
                'label' => \Craft::t('shipping-toolbox', 'Pickup address'),
            ],
            [
                'value' => 'PickupDate',
                'label' => \Craft::t('shipping-toolbox', 'Pickup date'),
            ],
            [
                'value' => 'ServiceList',
                'label' => \Craft::t('shipping-toolbox', 'Services and their special parameters.'),
            ],
            [
                'value' => 'PSDParameter',
                'label' => \Craft::t('shipping-toolbox', 'Parcel delivery shop code'),
            ],
        ];
    }

    public function getAddressJsonProperties()
    {
        return [
            [
                'value' => 'Name',
                'label' => \Craft::t('shipping-toolbox', 'Name of the person or organization.'),
            ],
            [
                'value' => 'Street',
                'label' => \Craft::t('shipping-toolbox', 'Street'),
            ],
            [
                'value' => 'HouseNumber',
                'label' => \Craft::t('shipping-toolbox', 'Number of the house'),
            ],
            [
                'value' => 'HouseNumberInfo',
                'label' => \Craft::t('shipping-toolbox', 'Additional house information.'),
            ],
            [
                'value' => 'City',
                'label' => \Craft::t('shipping-toolbox', 'Name of the town or village'),
            ],
            [
                'value' => 'ZipCode',
                'label' => \Craft::t('shipping-toolbox', 'Area Zip code'),
            ],
            [
                'value' => 'CountryIsoCode',
                'label' => \Craft::t('shipping-toolbox', 'Country code'),
            ],
            [
                'value' => 'ContactName',
                'label' => \Craft::t('shipping-toolbox', 'Contact person'),
            ],
            [
                'value' => 'ContactPhone',
                'label' => \Craft::t('shipping-toolbox', 'Contact phone number'),
            ],
            [
                'value' => 'ContactEmail',
                'label' => \Craft::t('shipping-toolbox', 'Contact email'),
            ],
        ];
    }

    public function getSavedProperty($property)
    {
        $value = $this->{$property} ?? null;
        if(is_null($value)){
            return null;
        }
        return $value;
    }

    public function getShippingDetails()
    {
        $properties = [];
        foreach ($this->getJsonProperties() as $single){
            $property = $single['value'];
            $propertyLabel = $single['label'];
            if($property == 'DeliveryAddress' || $property == 'PickupAddress'){
                $addressArray = $this->getSavedProperty($property);
                if(!is_array($addressArray)){
                    continue;
                }
                foreach($this->getAddressJsonProperties() as $addressSingle){
                    $addressProperty = $addressSingle['value'];
                    $addressLabel = $addressSingle['label'];
                    $addressValue = $addressArray[$addressProperty] ?? null;
                    $properties[] = [
                        'label' => $propertyLabel . ' - ' . $addressLabel,
                        'value' => $addressValue,
                    ];
                }
                continue;
            }
            $value = $this->getSavedProperty($property);
            // services
            if(is_array($value)){
                continue;
            }
            // date
            if($property == 'PickupDate'){
                $value = $this::decodeDateString($value);
            }
            $properties[] = [
                'label' => $propertyLabel,
                'value' => $value,
            ];
        }
        return $properties;
    }

    private static function decodeDateString($dateString)
    {
        if (preg_match('/\/Date\((\d+)([+-]\d{4})\)\//', $dateString, $matches)) {
            $timestamp = $matches[1]; // The timestamp in milliseconds
            $timezoneOffset = $matches[2]; // The timezone offset in the format ±HHMM

            $timestampInSeconds = $timestamp / 1000;
            $dateTime = new \DateTime("@$timestampInSeconds");

            // Convert the timezone offset to a format understood by DateTime
            $timezoneOffsetFormatted = substr($timezoneOffset, 0, 3) . ':' . substr($timezoneOffset, 3);
            $timezone = new \DateTimeZone($timezoneOffsetFormatted);
            $dateTime->setTimezone($timezone);
            $format = Craft::$app->getFormattingLocale()->getDateFormat('short', 'php');
            $string = $dateTime->format($format);
            return $string;
        }
        return null;
    }

    public function assignRequestData($request)
    {
        foreach($this->getJsonProperties() as $single){
            $property = $single['value'];

            // addresses
            if($property == 'DeliveryAddress' || $property == 'PickupAddress'){
                $addressArray = [];
                foreach($this->getAddressJsonProperties() as $addressSingle){
                    $addressProperty = $addressSingle['value'];
                    $addressArray[$addressProperty] = $request[$property][$addressProperty];
                }
                $this->{$property} = $addressArray;
                continue;
            }

            // parcel shop
            if($property == 'PSDParameter'){
                $services = $request['ServiceList'];
                $parcelShopService = array_filter($services, function($single){
                   return $single['Code'] == 'PSD';
                });
                if(!empty($parcelShopService)){
                    $parcelShopService = reset($parcelShopService);
                    $this->{$property} = $parcelShopService['PSDParameter']['StringValue'];
                }
                continue;
            }

            // all the rest
            $this->{$property} = $request[$property];
        }
    }

    public function encodeData()
    {
        // todo
        $parcels = array_map(function($single){
            $statuses = array_map(function($status){
                return [
                    'depotCity' => $status->depotCity,
                    'depotNumber' => $status->depotNumber,
                    'statusCode' => $status->statusCode,
                    'statusDate' => $status->statusDate,
                    'statusDescription' => $status->statusDescription,
                    'statusInfo' => $status->statusInfo,
                ];
            }, $single->statusObjects);
            return [
                'number' => $single->number,
                'id' => $single->id,
                'status' => $statuses,
            ];
        }, $this->parcels);
        $array = [
            'parcels' => $parcels,
        ];
        foreach ($this->getJsonProperties() as $single) {
            $property = $single['value'];
            $array[$property] = $this->{$property};
        }
        return json_encode($array);
    }

    public function isCod()
    {
        return $this->CODAmount > 0;
    }

    public function canRemoveParcels()
    {
        return true;
    }

    public function hasParcelShopCode()
    {
        $parcelShopCode = ShippingToolbox::getInstance()->plugins->getParcelShopCodeForOrder($this->order);
        return !is_null($parcelShopCode);
    }

    public function getParcelShopCode()
    {
        return ShippingToolbox::getInstance()->plugins->getParcelShopCodeForOrder($this->order);;
    }

    // PSD service requires ContactName, ContactPhone, ContactEmail
    public function hasRequiredParcelShopParams()
    {
        $address = $this->order->shippingAddress;

        // contact name is already checked during address validation

        $phoneField = ShippingToolbox::getInstance()->plugins->getPhoneField();
        if(is_null($phoneField) || empty($address->getFieldValue($phoneField->handle))){
            return false;
        }

        if(empty($this->order->email)){
            return false;
        }

        return true;
    }




}