<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * UpgradeSchema constructor.
     * @param WriterInterface $configWriter
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Upgrades DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        /**
         * On 3.6.0 we've added this setting to database, but it is not necessary because it is already
         * on config.xml file. So now this setting will be removed if has not been changed
         */
        if (version_compare($context->getVersion(), '3.7.0') < 0) {
            $asyncPaymentMethodsPath = 'signifyd/general/async_payment_methods';
            $asyncPaymentMethods = $this->scopeConfig->getValue($asyncPaymentMethodsPath);

            if ($asyncPaymentMethods == 'cybersource,adyen_cc') {
                $this->configWriter->delete($asyncPaymentMethodsPath);
            }
        }

        if (version_compare($context->getVersion(), '3.7.6') < 0) {
            $setup->getConnection()->addColumn($setup->getTable('signifyd_connect_case'), 'lock_start', [
                'type' => Table::TYPE_INTEGER,
                'nullable' => true,
                'default' => null,
                'comment' => 'Row lock start timestamp'
            ]);
        }

        $setup->endSetup();
    }
}
