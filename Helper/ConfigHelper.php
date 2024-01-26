<?php

/**
 * All configurations should be fetched using this helper in order to get the correct store configuration
 * on multistore environments
 */

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;

class ConfigHelper
{
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * Associative array of order_id => store_code
     * @var array
     */
    public $storeCodes = [];

    /**
     * Array of SignifydAPI, one for each store code
     *
     * @var array
     */
    public $signifydAPI = [];

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var OrderFactory
     */
    public $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    public $signifydOrderResourceModel;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * ConfigHelper constructor.
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param StoreManagerInterface $storeManager
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        StoreManagerInterface $storeManager,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        JsonSerializer $jsonSerializer
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->jsonSerializer = $jsonSerializer;
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
            $orderId = $entity->getOrderId();
        } elseif ($entity instanceof \Magento\Sales\Model\Order && $entity->isEmpty() == false) {
            $orderId = $entity->getId();
        } elseif ($entity instanceof \Magento\Quote\Model\Quote && $entity->isEmpty() == false) {
            return $this->getStoreIdFromQuote($entity);
        }

        if (isset($orderId)) {
            if (isset($this->storeCodes['order_' . $orderId])) {
                return $this->storeCodes['order_' . $orderId];
            } else {
                if ($entity instanceof \Signifyd\Connect\Model\Casedata) {
                    $order = $this->orderFactory->create();
                    $this->signifydOrderResourceModel->load($order, $entity->getData('order_id'));
                } else {
                    $order = $entity;
                }

                if ($order instanceof \Magento\Sales\Model\Order && $order->isEmpty() == false) {
                    $store = $this->storeManager->getStore($order->getStoreId());
                    $this->storeCodes['order_' . $orderId] = $store->getCode();
                    return $this->storeCodes['order_' . $orderId];
                }
            }
        }

        return $returnNullString ? '__null_signifyd_store__' : null;
    }

    public function getStoreIdFromQuote(\Magento\Quote\Model\Quote $entity)
    {
        $quoteId = $entity->getId();

        if (isset($quoteId)) {
            if (isset($this->storeCodes['quote_' . $quoteId])) {
                return $this->storeCodes['quote_' . $quoteId];
            } else {
                if ($entity instanceof \Signifyd\Connect\Model\Casedata) {
                    $order = $this->orderFactory->create();
                    $this->signifydOrderResourceModel->load($order, $entity->getData('order_id'));
                } else {
                    $order = $entity;
                }

                if ($order instanceof \Magento\Sales\Model\Order && $order->isEmpty() == false) {
                    $store = $this->storeManager->getStore($order->getStoreId());
                    $this->storeCodes['quote_' . $quoteId] = $store->getCode();
                    return $this->storeCodes['quote_' . $quoteId];
                }
            }
        }

        return null;
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

    public function getEnabledByStoreId($storeId = null)
    {
        $key = $this->scopeConfigInterface->getValue('signifyd/general/key', 'stores', $storeId);

        if (empty($key)) {
            return false;
        }

        return $this->scopeConfigInterface->isSetFlag('signifyd/general/enabled', 'stores', $storeId);
    }

    public function isScoreOnly()
    {
        return (bool) $this->scopeConfigInterface->getValue('signifyd/general/score_only');
    }

    public function getDecisionRequest()
    {
        return $this->scopeConfigInterface->getValue('signifyd/general/decision_request');
    }

    public function getCronBatchSize()
    {
        return $this->scopeConfigInterface->getValue('signifyd/advanced/cron_batch_size');
    }

    /**
     * Get restricted payment methods from store configs
     *
     * @return array|mixed
     */
    public function getRestrictedPaymentMethodsConfig()
    {
        $restrictedPaymentMethods = $this->getConfigData('signifyd/general/restrict_payment_methods');

        if (isset($restrictedPaymentMethods) === false) {
            return [];
        }

        $restrictedPaymentMethods = explode(',', $restrictedPaymentMethods);
        $restrictedPaymentMethods = array_map('trim', $restrictedPaymentMethods);

        return $restrictedPaymentMethods;
    }

    /**
     * Check if there is any restrictions by payment method or state
     *
     * @param $paymentMethodCode
     * @return bool
     */
    public function isPaymentRestricted($paymentMethodCode)
    {
        $restrictedPaymentMethods = $this->getRestrictedPaymentMethodsConfig();

        if (in_array($paymentMethodCode, $restrictedPaymentMethods)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function getIsOrderProcessedByAmazon(Order $order)
    {
        $paymentAction =  $this->scopeConfigInterface->getValue(
            'payment/amazon_payment_v2/payment_action',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $order->getStoreId()
        );

        if ($paymentAction === 'authorize_capture' && $order->hasInvoices() === false) {
            return false;
        }

        return true;
    }

    public function getDefaultPolicy($scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        return $this->scopeConfigInterface->getValue(
            'signifyd/general/policy_name',
            $scopeType,
            $scopeCode
        );
    }

    public function getPolicyName($scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        $defaultPolicyName = $this->getDefaultPolicy($scopeType, $scopeCode);
        $policyName = $this->scopeConfigInterface->getValue(
            'signifyd/advanced/policy_name',
            $scopeType,
            $scopeCode
        );

        if (isset($policyName)) {
            return $policyName;
        }

        return $defaultPolicyName;
    }

    /**
     * @param $policyName
     * @param $paymentMethod
     * @return bool
     */
    public function getIsPreAuth(
        $policyName,
        $paymentMethod,
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeCode = null
    ) {
        $policyFromMethod = $this->getPolicyFromMethod(
            $policyName,
            $paymentMethod,
            $scopeType,
            $scopeCode
        );

        return ($policyFromMethod == 'PRE_AUTH' || $policyFromMethod == 'SCA_PRE_AUTH');
    }

    /**
     * @param $policyName
     * @param $paymentMethod
     * @return int|mixed|string
     */
    public function getPolicyFromMethod(
        $policyName,
        $paymentMethod,
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeCode = null
    ) {
        if (isset($paymentMethod) === false) {
            return $this->getDefaultPolicy($scopeType, $scopeCode);
        }

        if ($this->isPaymentRestricted($paymentMethod)) {
            return $this->getDefaultPolicy($scopeType, $scopeCode);
        }

        try {
            $configPolicy = $this->jsonSerializer->unserialize($policyName);
        } catch (\InvalidArgumentException $e) {
            return $policyName;
        }

        foreach ($configPolicy as $key => $value) {
            if ($key == 'PRE_AUTH' || $key == 'SCA_PRE_AUTH' || $key == 'POST_AUTH') {
                if (is_array($value) === false) {
                    continue;
                }

                if (in_array($paymentMethod, $value)) {
                    return $key;
                }
            }
        }

        return $this->getDefaultPolicy($scopeType, $scopeCode);
    }

    /**
     * @param $policyName
     * @return bool
     */
    public function getIsPreAuthInUse($policyName)
    {
        try {
            $configPolicy = $this->jsonSerializer->unserialize($policyName);
        } catch (\InvalidArgumentException $e) {
            return ($policyName == 'PRE_AUTH');
        }

        if (isset($configPolicy['PRE_AUTH']) === false || is_array($configPolicy['PRE_AUTH']) === false) {
            return false;
        }

        return true;
    }
}
