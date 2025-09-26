<?php

namespace Signifyd\Connect\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Signifyd\Connect\Logger\Install;

class UpdateOrderId implements DataPatchInterface
{
    /**
     * @var SchemaSetupInterface
     */
    public $schemaSetupInterface;

    /**
     * @var Install
     */
    public $logger;

    /**
     * @var WriterInterface
     */
    public $configWriter;

    /**
     * UpdateOrderId construct.
     *
     * @param SchemaSetupInterface $schemaSetupInterface
     * @param Install $logger
     * @param WriterInterface $configWriter
     */
    public function __construct(
        SchemaSetupInterface $schemaSetupInterface,
        Install $logger,
        WriterInterface $configWriter
    ) {
        $this->schemaSetupInterface = $schemaSetupInterface;
        $this->logger = $logger;
        $this->configWriter = $configWriter;
    }

    /**
     * Apply method.
     *
     * @return $this|UpdateOrderId
     */
    public function apply()
    {
        $connection = $this->schemaSetupInterface->getConnection();
        $signifydConnectCase = $this->schemaSetupInterface->getTable('signifyd_connect_case');
        $salesOrder = $this->schemaSetupInterface->getTable('sales_order');

        try {
            $select = $connection->select()
                ->from(['soc' => $salesOrder], ['increment_id', 'entity_id'])
                ->joinInner(
                    ['so' => $signifydConnectCase],
                    'so.order_increment = soc.increment_id',
                    ['case_id' => 'so.entity_id', 'magento_status']
                );

            $rows = $connection->fetchAll($select);

            foreach ($rows as $row) {
                try {
                    $connection->update(
                        $signifydConnectCase,
                        ['order_id' => $row['entity_id']],
                        [
                            'order_increment = ?' => $row['increment_id'],
                            'magento_status ' .
                            ($row['magento_status'] === 'complete' ? '= ?' : '<> ?') => $row['magento_status']
                        ]
                    );
                } catch (\Exception $e) {
                    $this->logger->debug('Failed updating order_id for increment ' . $row['increment_id']);
                    $this->configWriter->save("signifyd/general/upgrade4.3_inconsistency", "setup");
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('General failure updating order_id relations');
            $this->configWriter->save("signifyd/general/upgrade4.3_inconsistency", "setup");
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [

        ];
    }
}
