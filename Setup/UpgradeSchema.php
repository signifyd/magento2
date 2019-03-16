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
use Signifyd\Connect\Observer\Purchase;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var \Signifyd\Connect\Helper\ConfigHelper
     */
    protected $configWriter;

    /**
     * @var Purchase
     */
    protected $purchaseObserver;

    /**
     * TODO: Make changes  to be able to save current restricted payment gateways from code to database
     *
     * InstallSchema constructor.
     * @param \Signifyd\Connect\Helper\ConfigHelper $configHelper
     */
    public function __construct(
        WriterInterface $configWriter,
        Purchase $purchaseObserver
    ) {
        $this->configWriter = $configWriter;
        $this->purchaseObserver = $purchaseObserver;
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

        if (version_compare($context->getVersion(), '3.0.0') < 0) {
            $setup->getConnection()->addColumn($setup->getTable('sales_order'), 'origin_store_code', [
                'type' => Table::TYPE_TEXT,
                'LENGTH' => 32,
                'nullable' => true,
                'comment' => 'Store code used to place order',
            ]);
        }

        if (version_compare($context->getVersion(), '3.0.4') == -1) {
            $setup->getConnection()->addColumn($setup->getTable('signifyd_connect_case'), 'retries', [
                'type' => Table::TYPE_INTEGER,
                'nullable' => false,
                'default' => 0,
                'comment' => 'Number of retries for current case magento_status',
            ]);
        }

        if (version_compare($context->getVersion(), '3.2.0') < 0) {
            $oldRestrictedPaymentMethods = $this->purchaseObserver->getOldRestrictMethods();

            if (is_array($oldRestrictedPaymentMethods) &&
                empty($oldRestrictedPaymentMethods) == false) {

                $restrictedPaymentMethods = $this->purchaseObserver->getRestrictedPaymentMethodsConfig();

                $diff1 = array_diff($oldRestrictedPaymentMethods, $restrictedPaymentMethods);
                $diff2 = array_diff($restrictedPaymentMethods, $oldRestrictedPaymentMethods);

                // If anything is different, so use $oldRestrictedPaymentMethods on database settings
                if (empty($diff1) == false || empty($diff2) == false) {
                    $oldRestrictedPaymentMethods = implode(',', $oldRestrictedPaymentMethods);
                    $this->configWriter->save('signifyd/general/restrict_payment_methods', $oldRestrictedPaymentMethods);
                }
            }
        }

        $setup->endSetup();
    }
}
