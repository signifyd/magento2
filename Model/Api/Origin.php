<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\ResourceModel\Region as RegionResourceModel;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Origin
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var RegionFactory
     */
    protected $regionFactory;

    /**
     * @var RegionResourceModel
     */
    protected $regionResourceModel;

    /**
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param RegionFactory $regionFactory
     * @param RegionResourceModel $regionResourceModel
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        RegionFactory $regionFactory,
        RegionResourceModel $regionResourceModel
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->regionFactory = $regionFactory;
        $this->regionResourceModel = $regionResourceModel;
    }

    /**
     * Construct a new Origin object
     * @param $storeId
     * @return array
     */
    public function __invoke($storeId)
    {
        $streetAddress = $this->scopeConfigInterface->getValue(
            'general/store_information/street_line1',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $postalCode = $this->scopeConfigInterface->getValue(
            'general/store_information/postcode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $city = $this->scopeConfigInterface->getValue(
            'general/store_information/city',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $provinceId = $this->scopeConfigInterface->getValue(
            'general/store_information/region_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $countryCode = $this->scopeConfigInterface->getValue(
            'general/store_information/country_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        if (isset($streetAddress) === false ||
            isset($postalCode) === false ||
            isset($city) === false
        ) {
            return null;
        }

        if (isset($provinceId)) {
            $magentoRegion = $this->regionFactory->create();
            $this->regionResourceModel->load($magentoRegion, $provinceId);
            $provinceCode = $magentoRegion->getCode();
        } else {
            $provinceCode = null;
        }

        $origin = [];
        $origin['locationId'] = $storeId;
        $origin['address'] = [
            'streetAddress' => $streetAddress,
            'postalCode' => $postalCode,
            'city' => $city,
            'provinceCode' => $provinceCode,
            'countryCode' => $countryCode ?? null
        ];

        return $origin;
    }
}