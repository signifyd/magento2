<?php

namespace Signifyd\Connect\Model\Api;

class Address
{
    /**
     * Construct a new Address object
     *
     * @param mixed $mageAddress
     * @return array
     */
    public function __invoke($mageAddress)
    {
        $address = [];

        $address['streetAddress'] = $mageAddress->getStreetLine(1);
        $address['unit'] = $mageAddress->getStreetLine(2);
        $address['postalCode'] = $mageAddress->getPostcode();
        $address['city'] = $mageAddress->getCity();
        $address['provinceCode'] = $mageAddress->getRegionCode();
        $address['countryCode'] = $mageAddress->getCountryId();

        return $address;
    }
}
