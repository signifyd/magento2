<?php
namespace Signifyd\Connect\Model\ScaPreAuth;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\PurchaseHelper;
use Magento\Framework\Registry;

class ScaEvaluationConfig extends AbstractModel
{
    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param PurchaseHelper $purchaseHelper
     * @param ConfigHelper $configHelper
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        PurchaseHelper $purchaseHelper,
        ConfigHelper $configHelper,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->purchaseHelper = $purchaseHelper;
        $this->configHelper = $configHelper;
    }

    /**
     * @param int $storeId
     * @param string $paymentMethod
     * @return bool
     */
    public function isScaEnabled($storeId, $paymentMethod)
    {
        $policyName = $this->purchaseHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );
        $policyFromMethod = $this->purchaseHelper->getPolicyFromMethod(
            $policyName,
            $paymentMethod,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );
        $isScaPreAuth = ($policyFromMethod == 'SCA_PRE_AUTH');

        if ($this->configHelper->getEnabledByStoreId($storeId) === false ||
            $isScaPreAuth === false
        ) {
            return false;
        }

        return true;
    }
}
