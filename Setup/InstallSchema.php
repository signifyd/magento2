<?php
/**
 * Copyright © 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

/**
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        if (!$installer->tableExists('signifyd_connect_case')) {
            $table = $installer->getConnection()->newTable($installer->getTable('signifyd_connect_case'));
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
                    'signifyd_status',
                    Table::TYPE_TEXT,
                    255,
                    [
                        'nullable' => false,
                        'default' => 'PENDING'
                    ],
                    'Signifyd Status'
                )
                ->addColumn(
                    'code',
                    Table::TYPE_TEXT,
                    255,
                    [
                        'nullable' => false,
                    ],
                    'Code'
                )
                ->addColumn(
                    'score',
                    Table::TYPE_FLOAT,
                    null,
                    [],
                    'Score'
                )
                ->addColumn(
                    'guarantee',
                    Table::TYPE_TEXT,
                    64,
                    [
                        'nullable' => false,
                        'default' => 'N/A'
                    ],
                    'Guarantee Status'
                )
                ->addColumn(
                    'entries_text',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false],
                    'Entries'
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
                    'Update Time'
                )
                ->setComment('Signifyd Cases');
            $installer->getConnection()->createTable($table);
        }
    }
}
