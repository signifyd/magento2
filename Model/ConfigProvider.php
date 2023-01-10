<?php
/**
 * Created by PhpStorm.
 * User: Ion Bogatu
 * Date: 5/9/2018
 * Time: 4:54 PM
 */

namespace Signifyd\Connect\Model;

use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Helper\PurchaseHelper;
use Magento\Framework\Module\ModuleListInterface;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ModuleListInterface
     */
    protected $moduleListInterface;

    /**
     * @param PurchaseHelper $purchaseHelper
     * @param StoreManagerInterface $storeManager
     * @param ModuleListInterface $moduleListInterface
     */
    public function __construct(
        PurchaseHelper $purchaseHelper,
        StoreManagerInterface $storeManager,
        ModuleListInterface $moduleListInterface
    ) {
        $this->purchaseHelper = $purchaseHelper;
        $this->storeManager = $storeManager;
        $this->moduleListInterface = $moduleListInterface;
    }
    public function getConfig()
    {
        $policyName = $this->purchaseHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getCode()
        );

        $isAdyenGreaterThanEightEleven = true;
        $adyenModule = $this->moduleListInterface->getOne('Adyen_Payment');

        if (isset($adyenModule)) {
            $adyenVersion = $this->moduleListInterface->getOne('Adyen_Payment')['setup_version'];
            $isAdyenGreaterThanEightEleven = version_compare($adyenVersion, '8.11.0') >= 0;
        }

        $isAdyenPreAuth = $this->purchaseHelper->getIsPreAuth($policyName, 'adyen_cc');

        return [ 'signifyd' => [
            'isAdyenPreAuth' => $isAdyenPreAuth,
            'isAdyenGreaterThanEightEleven' => $isAdyenGreaterThanEightEleven]
        ];
    }
}
