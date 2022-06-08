<?php

/**
 * All configurations should be fetched using this helper in order to get the correct store configuration
 * on multistore environments
 */

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Core\Api\SaleApiFactory;
use Signifyd\Core\Api\CaseApiFactory;
use Signifyd\Core\Api\CheckoutApiFactory;
use Signifyd\Core\Api\GuaranteeApiFactory;
use Signifyd\Core\Api\WebhooksApiFactory;
use Magento\Framework\Filesystem\DirectoryList;

class ConfigHelper
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * Associative array of order_id => store_code
     * @var array
     */
    protected $storeCodes = [];

    /**
     * Array of SignifydAPI, one for each store code
     *
     * @var array
     */
    protected $signifydAPI = [];

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CaseApiFactory
     */
    protected $caseApiFactory;

    /**
     * @var SaleApiFactory
     */
    protected $saleApiFactory;

    /**
     * @var CheckoutApiFactory
     */
    protected $checkoutApiFactory;

    /**
     * @var GuaranteeApiFactory
     */
    protected $guaranteeApiFactory;

    /**
     * @var WebhooksApiFactory
     */
    protected $webhooksApiFactory;

    /**
     * @var DirectoryList
     */
    protected $directory;

    /**
     * ConfigHelper constructor.
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param StoreManagerInterface $storeManager
     * @param CaseApiFactory $caseApiFactory
     * @param CheckoutApiFactory $checkoutApiFactory
     * @param SaleApiFactory $saleApiFactory
     * @param GuaranteeApiFactory $guaranteeApiFactory
     * @param WebhooksApiFactory $webhooksApiFactory
     * @param DirectoryList $directory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        StoreManagerInterface $storeManager,
        CaseApiFactory $caseApiFactory,
        SaleApiFactory $saleApiFactory,
        CheckoutApiFactory $checkoutApiFactory,
        GuaranteeApiFactory $guaranteeApiFactory,
        WebhooksApiFactory $webhooksApiFactory,
        DirectoryList $directory
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->storeManager = $storeManager;
        $this->caseApiFactory = $caseApiFactory;
        $this->saleApiFactory = $saleApiFactory;
        $this->checkoutApiFactory = $checkoutApiFactory;
        $this->guaranteeApiFactory = $guaranteeApiFactory;
        $this->webhooksApiFactory = $webhooksApiFactory;
        $this->directory = $directory;
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
                $order = $entity instanceof \Signifyd\Connect\Model\Casedata ? $entity->getOrder() : $entity;

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
                $order = $entity instanceof \Signifyd\Connect\Model\Casedata ? $entity->getOrder() : $entity;

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
     * @param string $type
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return mixed
     */
    public function getSignifydApi($type, \Magento\Framework\Model\AbstractModel $entity = null)
    {
        $type = strtolower($type);
        $apiId = $type . $this->getStoreCode($entity, true);

        if (isset($this->signifydAPI[$apiId]) === false ||
            is_object($this->signifydAPI[$apiId]) === false) {
            $apiKey = $this->getConfigData('signifyd/general/key', $entity);
            $args = [
                'apiKey' => $apiKey,
                'logLocation' => $this->directory->getPath('log')
            ];

            switch ($type) {
                case 'case':
                    $this->signifydAPI[$apiId] = $this->caseApiFactory->create(['args' => $args]);
                    break;

                case 'sale':
                    $this->signifydAPI[$apiId] = $this->saleApiFactory->create(['args' => $args]);
                    break;

                case 'checkout':
                    $this->signifydAPI[$apiId] = $this->checkoutApiFactory->create(['args' => $args]);
                    break;

                case 'guarantee':
                    $this->signifydAPI[$apiId] = $this->guaranteeApiFactory->create(['args' => $args]);
                    break;

                case 'webhook':
                    $this->signifydAPI[$apiId] = $this->webhooksApiFactory->create(['args' => $args]);
                    break;
            }
        }

        return $this->signifydAPI[$apiId];
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return \Signifyd\Core\Api\CaseApi
     */
    public function getSignifydCaseApi(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        return $this->getSignifydApi('case', $entity);
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return \Signifyd\Core\Api\CheckoutApi
     */
    public function getSignifydCheckoutApi(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        return $this->getSignifydApi('checkout', $entity);
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return \Signifyd\Core\Api\ApiModel
     */
    public function getSignifydSaleApi(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        return $this->getSignifydApi('sale', $entity);
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return \Signifyd\Core\Api\GuaranteeApi
     */
    public function getSignifydGuaranteeApi(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        return $this->getSignifydApi('guarantee', $entity);
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return \Signifyd\Core\Api\WebhooksApi
     */
    public function getSignifydWebhookApi(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        return $this->getSignifydApi('webhook', $entity);
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

    public function getGuaranteesReviewedAction()
    {
        return $this->scopeConfigInterface->getValue('signifyd/advanced/guarantees_reviewed_action');
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
     * @param $method
     * @param null $state
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
}
