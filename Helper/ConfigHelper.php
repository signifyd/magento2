<?php

/**
 * All configurations should be fetched using this helper in order to get the correct store configuration
 * on multistore environments
 */

namespace Signifyd\Connect\Helper;

class ConfigHelper
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * Associative array of order_increment_id => store_code
     * @var array
     */
    protected $storeCodes = [];

    /**
     * @var SignifydSettingsMagentoFactory
     */
    protected $signifydSettingsMagentoFactory;

    /**
     * Array of SignifydSettingsMagento, one for each store code
     *
     * @var array
     */
    protected $signifydSettingsMagento = [];

    /**
     * @var \Signifyd\Core\SignifydAPIFactory
     */
    protected $signifydAPIFactory;

    /**
     * Array of SignifydAPI, one for each store code
     *
     * @var array
     */
    protected $signifydAPI = [];

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * ConfigHelper constructor.
     * @param ScopeConfigInterface $scopeConfigInterface
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
        \Signifyd\Connect\Helper\SignifydSettingsMagentoFactory $signifydSettingsMagentoFactory,
        \Signifyd\Connect\Api\Core\SignifydAPIFactory $signifydAPIFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->signifydSettingsMagentoFactory = $signifydSettingsMagentoFactory;
        $this->signifydAPIFactory = $signifydAPIFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * Retrieve store configuration for order store
     *
     * @param $path
     * @param \Magento\Sales\Model\Order|null $entity
     * @return mixed
     */
    public function getConfigData($path, \Magento\Framework\Model\AbstractModel $entity = null, $flag = false)
    {
        $storeCode = $this->getStoreCode($entity);

        if ($flag == true) {
            $config = $this->scopeConfigInterface->isSetFlag($path, 'stores', $storeCode);
        } else {
            $config = $this->scopeConfigInterface->getValue($path, 'stores', $storeCode);
        }

        return $config;
    }

    /**
     * Given entity returns store code
     *
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return bool|int|mixed|null|string
     */
    public function getStoreCode(\Magento\Framework\Model\AbstractModel $entity = null, $returnNullString = false)
    {
        if ($entity instanceof \Signifyd\Connect\Model\Casedata && $entity->isEmpty() == false) {
            $incrementId = $entity->getOrderIncrement();
        } elseif ($entity instanceof \Magento\Sales\Model\Order && $entity->isEmpty() == false) {
            $incrementId = $entity->getIncrementId();
        }

        if (isset($incrementId)) {
            if (isset($this->storeCodes[$incrementId])) {
                return $this->storeCodes[$incrementId];
            } else {
                $order = $entity instanceof \Signifyd\Connect\Model\Casedata ? $entity->getOrder() : $entity;

                if ($order instanceof \Magento\Sales\Model\Order && $entity->isEmpty() == false) {
                    $store = $this->storeManager->getStore($entity->getStoreId());
                    $this->storeCodes[$incrementId] = $store->getCode();
                    return $this->storeCodes[$incrementId];
                }
            }
        }

        return $returnNullString ? '__null_signifyd_store__' : null;
    }

    /**
     * Retrieve settings for Signifyd given entity (order os case)
     *
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return mixed
     */
    public function getSignifydSettingsMagento(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        $storeCode = $this->getStoreCode($entity, true);

        if (isset($this->signifydSettingsMagento[$storeCode]) === false ||
            $this->signifydSettingsMagento[$storeCode] instanceof SignifydSettingsMagento == false) {
            $this->signifydSettingsMagento[$storeCode] = $this->signifydSettingsMagentoFactory->create();
            $apiKey = $this->getConfigData('signifyd/general/key', $entity);

            $this->signifydSettingsMagento[$storeCode]->apiKey = $apiKey;
        }

        return $this->signifydSettingsMagento[$storeCode];
    }

    /**
     * Retrieve Signifyd API object given entity (order os case)
     *
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return \Signifyd\Core\SignifydAPI
     */
    public function getSignifydApi(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        $storeCode = $this->getStoreCode($entity, true);

        if (isset($this->signifydAPI[$storeCode]) === false ||
            $this->signifydAPI[$storeCode] instanceof \Signifyd\Core\SignifydAPI == false) {
            $signifydSettingsMagento = $this->getSignifydSettingsMagento($entity);
            $this->signifydAPI[$storeCode] = $this->signifydAPIFactory->create(array('settings' => $signifydSettingsMagento));
        }

        return $this->signifydAPI[$storeCode];
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return mixed
     */
    public function isEnabled(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        $key = $this->getConfigData('signifyd/general/key', $entity);

        if (empty($key)) {
            return false;
        }

        return $this->getConfigData('signifyd/general/enabled', $entity, true);
    }
}