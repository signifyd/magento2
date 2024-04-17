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
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Directory\ReadFactory;

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
    protected ComponentRegistrarInterface $componentRegistrar;
    protected ReadFactory $readFactory;

    /**
     * @param ConfigHelper $configHelper
     * @param StoreManagerInterface $storeManager
     * @param ModuleListInterface $moduleListInterface
     */
    public function __construct(
        ReadFactory $readFactory,
        ComponentRegistrarInterface $componentRegistrar,
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager,
        ModuleListInterface $moduleListInterface
    ) {
        $this->storeManager = $storeManager;
        $this->moduleListInterface = $moduleListInterface;
        $this->configHelper = $configHelper;
        $this->componentRegistrar = $componentRegistrar;
        $this->readFactory = $readFactory;
    }
    public function getConfig()
    {
        $policyName = $this->configHelper->getPolicyName(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getCode()
        );

        $isAdyenGreaterThanEightEighteen = false;
        $isAdyenGreaterThanEight = false;
        $adyenModule = $this->moduleListInterface->getOne('Adyen_Payment');

        if (isset($adyenModule)) {
            $adyenVersion = $this->getModuleVersionFromComposer('Adyen_Payment');
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
     * Get version from composer.json if it exist otherwise return empty string
     * @param string $moduleName
     * @return string
     */
    public function getModuleVersionFromComposer(string $moduleName) : string
    {
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
        $directoryRead = $this->readFactory->create($path);
        $composerJsonData = $directoryRead->readFile('composer.json');
        $data = json_decode($composerJsonData, true);
        if (is_null($data))
        {
            return '';
        }

        return isset($data['version']) ? $data['version'] : '';
    }
}
