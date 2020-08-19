<?php

namespace Signifyd\Connect\Setup\SchemaModification;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\SchemaSetupInterface;
use Zend_Db_Exception;

class AddTableColumns
{
    /**
     * @param SchemaSetupInterface $setup
     * @throws Zend_Db_Exception
     */
    public function execute(SchemaSetupInterface $setup)
    {
        $tablesToUpdate = [
            $setup->getTable('sales_order'),
            $setup->getTable('sales_order_grid'),
        ];

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

        try {
            $connection = $setup->getConnection();
            foreach ($tablesToUpdate as $table) {
                foreach ($columns as $name => $definition) {
                    $connection->addColumn($table, $name, $definition);
                }
            }

        } catch (\Exception $e) {
            throw new Zend_Db_Exception('Error modifying sales_order table: ' . $e->getMessage());
        }
    }
}
