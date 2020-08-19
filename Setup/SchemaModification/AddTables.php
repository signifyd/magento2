<?php

namespace Signifyd\Connect\Setup\SchemaModification;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\SchemaSetupInterface;
use Signifyd\Connect\Setup\InstallSchema;
use Zend_Db_Exception;

class AddTables
{
    /**
     * @param SchemaSetupInterface $setup
     * @throws Zend_Db_Exception
     */
    public function execute(SchemaSetupInterface $setup)
    {
        if (!$setup->tableExists(InstallSchema::TABLE_SIGNIFYD_CONNECT_CASE)) {
            $table = $setup->getConnection()->newTable($setup->getTable(InstallSchema::TABLE_SIGNIFYD_CONNECT_CASE));
            $table->addColumn(
                'order_increment',
                Table::TYPE_TEXT,
                255,
                [
                    'nullable' => false,
                    'primary' => true
                ],
                'Order ID'
            )->addColumn(
                'signifyd_status',
                Table::TYPE_TEXT,
                255,
                [
                    'nullable' => false,
                    'default' => 'PENDING'
                ],
                'Signifyd Status'
            )->addColumn(
                'code',
                Table::TYPE_TEXT,
                255,
                [
                    'nullable' => false,
                ],
                'Code'
            )->addColumn(
                'score',
                Table::TYPE_FLOAT,
                null,
                [],
                'Score'
            )->addColumn(
                'guarantee',
                Table::TYPE_TEXT,
                64,
                [
                    'nullable' => false,
                    'default' => 'N/A'
                ],
                'Guarantee Status'
            )->addColumn(
                'entries_text',
                Table::TYPE_TEXT,
                null,
                [
                    'nullable' => false
                ],
                'Entries'
            )->addColumn(
                'created',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Creation Time'
            )->addColumn(
                'updated',
                Table::TYPE_TIMESTAMP,
                null,
                [],
                'Update Time'
            )->addColumn(
                'magento_status',
                Table::TYPE_TEXT,
                255,
                [
                    'nullable' => false,
                    'default' => 'waiting_submission'
                ],
                'Magento Status'
            )->setComment('Signifyd Cases');
            $setup->getConnection()->createTable($table);
        }
    }
}
