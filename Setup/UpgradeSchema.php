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

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * Upgrades DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '1.0.1') < 0) {

            // Get module table
            $tableName = $setup->getTable('sales_order');
            $gridTableName = $setup->getTable('sales_order_grid');

            // Check if the table already exists
            if ($setup->getConnection()->isTableExists($tableName)) {
                // Declare data
                $columns = [
                    'signifyd_score' => [
                        'type' => Table::TYPE_FLOAT,
                        'default' => null,
                        'comment' => 'Score',
                    ],
                    'signifyd_guarantee' => [
                        'type' => Table::TYPE_TEXT,
                        'LENGTH' => 64,
                        'default' => 'N/A',
                        'nullable' => false,
                        'comment' => 'Guarantee Status',
                    ],
                    'signifyd_code' => [
                        'type' => Table::TYPE_TEXT,
                        'LENGTH' => 255,
                        'default' => '',
                        'nullable' => false,
                        'comment' => 'Code',
                    ],
                ];

                $connection = $setup->getConnection();
                foreach ($columns as $name => $definition) {
                    $connection->dropColumn($tableName, $name);
                    $connection->addColumn($tableName, $name, $definition);
                    $connection->addColumn($gridTableName, $name, $definition);
                }

            }
        }
        if(version_compare($context->getVersion(), '1.0.2') < 0)
        {
            if (!$setup->tableExists('signifyd_connect_retries')) {
                $table = $setup->getConnection()->newTable($setup->getTable('signifyd_connect_retries'));
                $table->addColumn(
                    'order_increment',
                    Table::TYPE_TEXT,
                    255,
                    [
                        'nullable' => false,
                        'primary' => true
                    ],
                    'Order ID'
                )
                    ->addColumn(
                        'created',
                        Table::TYPE_TIMESTAMP,
                        null,
                        [],
                        'Creation Time'
                    )
                    ->addColumn(
                        'updated',
                        Table::TYPE_TIMESTAMP,
                        null,
                        [],
                        'Last Attempt'
                    )
                    ->setComment('Signifyd Retries');
                $setup->getConnection()->createTable($table);
            }
        }

        if(version_compare($context->getVersion(), '1.3.5') < 0) {
            // Get table Name
            $tableName = $setup->getTable('signifyd_connect_case');
            // Check if the table already exists
            if ($setup->getConnection()->isTableExists($tableName)) {
                // Declare data
                $columns = [
                    'magento_status' => [
                        'type' => Table::TYPE_TEXT,
                        'LENGTH' => 255,
                        'default' => 'waiting_submission',
                        'nullable' => false,
                        'comment' => 'Magento Status',
                    ]
                ];

                $connection = $setup->getConnection();
                foreach ($columns as $name => $definition) {
                    $connection->addColumn($tableName, $name, $definition);
                }

            }
        }

        $setup->endSetup();
    }
}
