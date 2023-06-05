<?php
/**
 * Created by PhpStorm.
 * User: Ion Bogatu
 * Date: 5/9/2018
 * Time: 4:54 PM
 */

namespace Signifyd\Connect\Model;

use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Magento\Framework\Module\ModuleListInterface;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ModuleListInterface
     */
    protected $moduleListInterface;

    /**
     * @param ConfigHelper $configHelper
     * @param StoreManagerInterface $storeManager
     * @param ModuleListInterface $moduleListInterface
     */
    public function __construct(
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager,
        ModuleListInterface $moduleListInterface
    ) {
        $this->storeManager = $storeManager;
        $this->moduleListInterface = $moduleListInterface;
        $this->configHelper = $configHelper;
    }
    public function getConfig()
    {
        $policyName = $this->configHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getCode()
        );

        $isAdyenGreaterThanEightEleven = true;
        $adyenModule = $this->moduleListInterface->getOne('Adyen_Payment');

        if (isset($adyenModule)) {
            $adyenVersion = $this->moduleListInterface->getOne('Adyen_Payment')['setup_version'];
            $isAdyenGreaterThanEightEleven = version_compare($adyenVersion, '8.11.0') >= 0;
        }

        $isAdyenPreAuth = $this->configHelper->getIsPreAuth(
            $policyName,
            'adyen_cc',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getCode()
        );

        return [ 'signifyd' => [
            'isAdyenPreAuth' => $isAdyenPreAuth,
            'isAdyenGreaterThanEightEleven' => $isAdyenGreaterThanEightEleven]
        ];
    }
}
