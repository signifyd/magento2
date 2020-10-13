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
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Purchase
     */
    protected $purchaseObserver;

    /**
     * UpgradeSchema constructor.
     * @param WriterInterface $configWriter
     * @param ScopeConfigInterface $scopeConfig
     * @param Purchase $purchaseObserver
     */
    public function __construct(
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        Purchase $purchaseObserver
    ) {
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
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
                    $restrictedPaymentMethodsPath = 'signifyd/general/restrict_payment_methods';
                    $this->configWriter->save($restrictedPaymentMethodsPath, $oldRestrictedPaymentMethods);
                }
            }
        }

        if (version_compare($context->getVersion(), '3.2.1') < 0) {
            if ($setup->tableExists('signifyd_connect_retries')) {
                $setup->getConnection()->dropTable('signifyd_connect_retries');
            }
        }

        if (version_compare($context->getVersion(), '3.3.0') < 0) {
            if ($setup->tableExists('signifyd_connect_fulfillment') == false) {
                $table = $setup->getConnection()->newTable($setup->getTable('signifyd_connect_fulfillment'));
                $table
                    ->addColumn(
                        'id',
                        Table::TYPE_TEXT,
                        50,
                        ['nullable' => false, 'primary' => true],
                        'Fulfillment (Shipment) ID'
                    )
                    ->addColumn(
                        'order_id',
                        Table::TYPE_TEXT,
                        32,
                        ['nullable' => false],
                        'Order ID'
                    )
                    ->addColumn(
                        'created_at',
                        Table::TYPE_TEXT,
                        30,
                        ['nullable' => false],
                        'Created at'
                    )
                    ->addColumn(
                        'delivery_email',
                        Table::TYPE_TEXT,
                        255,
                        [
                            'nullable' => true,
                            'default' => null
                        ],
                        'Delivery e-mail'
                    )
                    ->addColumn(
                        'fulfillment_status',
                        Table::TYPE_TEXT,
                        30,
                        [
                            'nullable' => false
                        ],
                        'Fulfillment status'
                    )
                    ->addColumn(
                        'tracking_numbers',
                        Table::TYPE_TEXT,
                        255,
                        [
                            'nullable' => true
                        ],
                        'Tracking numbers'
                    )
                    ->addColumn(
                        'tracking_urls',
                        Table::TYPE_TEXT,
                        null,
                        [
                            'nullable' => true
                        ],
                        'Traching URLs'
                    )
                    ->addColumn(
                        'products',
                        Table::TYPE_TEXT,
                        false,
                        [
                            'nullable' => true
                        ],
                        'Products'
                    )
                    ->addColumn(
                        'shipment_status',
                        Table::TYPE_TEXT,
                        30,
                        [
                            'nullable' => true
                        ],
                        'Shipment status'
                    )
                    ->addColumn(
                        'delivery_address',
                        Table::TYPE_TEXT,
                        null,
                        [
                            'nullable' => true
                        ],
                        'Delivery address'
                    )
                    ->addColumn(
                        'recipient_name',
                        Table::TYPE_TEXT,
                        255,
                        [
                            'nullable' => true
                        ],
                        'Recipient name'
                    )
                    ->addColumn(
                        'confirmation_name',
                        Table::TYPE_TEXT,
                        255,
                        [
                            'nullable' => true
                        ],
                        'Confirmation name'
                    )
                    ->addColumn(
                        'confirmation_phone',
                        Table::TYPE_TEXT,
                        50,
                        [
                            'nullable' => true
                        ],
                        'Confirmation phone'
                    )
                    ->addColumn(
                        'shipping_carrier',
                        Table::TYPE_TEXT,
                        255,
                        [
                            'nullable' => true
                        ],
                        'Shipping carrier'
                    )
                    ->addColumn(
                        'magento_status',
                        Table::TYPE_TEXT,
                        50,
                        ['nullable' => false, 'default' => 'waiting_submission'],
                        'Magento Status'
                    )
                    ->setComment('Signifyd Fulfillments');
                $setup->getConnection()->createTable($table);
            }
        }

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

        $setup->endSetup();
    }
}
