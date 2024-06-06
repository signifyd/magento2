<?php
/**
 * Created by PhpStorm.
 * User: Ion Bogatu
 * Date: 5/9/2018
 * Time: 4:54 PM
 */

namespace Signifyd\Connect\Model;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Magento\Framework\Module\ModuleListInterface;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var ModuleListInterface
     */
    public $moduleListInterface;

    /**
     * @var ComponentRegistrarInterface
     */
    public $componentRegistrar;

    /**
     * ConfigProvider constructor
     *
     * @param ConfigHelper $configHelper
     * @param StoreManagerInterface $storeManager
     * @param ModuleListInterface $moduleListInterface
     * @param ComponentRegistrarInterface $componentRegistrar
     */
    public function __construct(
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager,
        ModuleListInterface $moduleListInterface,
        ComponentRegistrarInterface $componentRegistrar
    ) {
        $this->storeManager = $storeManager;
        $this->moduleListInterface = $moduleListInterface;
        $this->configHelper = $configHelper;
        $this->componentRegistrar = $componentRegistrar;
    }

    /**
     * Get config
     *
     * @return array[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConfig()
    {
        $policyName = $this->configHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getCode()
        );

        $isAdyenGreaterThanEightEighteen = false;
        $isAdyenGreaterThanEight = false;
        $adyenVersion = $this->getAdyenModuleVersion();

        if (isset($adyenVersion)) {
            $isAdyenGreaterThanEightEighteen = version_compare($adyenVersion, '8.18.0') >= 0;
            $isAdyenGreaterThanEight = version_compare($adyenVersion, '8.0.0') >= 0 &&
                version_compare($adyenVersion, '8.17.9') <= 0;
        }

        $isAdyenPreAuth = $this->configHelper->getIsPreAuth(
            $policyName,
            'adyen_cc',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getCode()
        );

        return [ 'signifyd' => [
            'isAdyenPreAuth' => $isAdyenPreAuth,
            'isAdyenGreaterThanEightEighteen' => $isAdyenGreaterThanEightEighteen,
            'isAdyenGreaterThanEight' => $isAdyenGreaterThanEight]
        ];
    }

    /**
     * Get Adyen module version
     *
     * @return mixed|null
     */
    public function getAdyenModuleVersion()
    {
        $moduleDir = $this->componentRegistrar->getPath(
            ComponentRegistrar::MODULE,
            'Adyen_Payment'
        );

        if (isset($moduleDir) === false) {
            return null;
        }

        $composerJson = file_get_contents($moduleDir . '/composer.json');
        $composerJson = json_decode($composerJson, true);

        if (empty($composerJson['version'])) {
            return null;
        }

        return $composerJson['version'];
    }
}
