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
    protected $schemaSetupInterface;

    /**
     * @var Install
     */
    protected $logger;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
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

    public function apply()
    {
        $signifydConnectCase = $this->schemaSetupInterface->getTable('signifyd_connect_case');
        $salesOrder = $this->schemaSetupInterface->getTable('sales_order');

        try {
            $this->schemaSetupInterface->getConnection()->query(
                "UPDATE ". $signifydConnectCase ." JOIN " . $salesOrder . " ON " .
                $signifydConnectCase .".order_increment = " . $salesOrder . ".increment_id SET " .
                $signifydConnectCase .".order_id = " . $salesOrder . ".entity_id WHERE " .
                $signifydConnectCase . ".magento_status='complete'"
            );
        } catch (\Exception $e) {
            $this->logger->debug('Update order_id on magento status complete failed');
            $this->configWriter->save("signifyd/general/upgrade4.3_inconsistency", "setup");
        }

        try {
            $this->schemaSetupInterface->getConnection()->query(
                "UPDATE ". $signifydConnectCase ." JOIN " . $salesOrder . " ON " .
                $signifydConnectCase .".order_increment = " . $salesOrder . ".increment_id SET ".
                $signifydConnectCase .".order_id = " . $salesOrder . ".entity_id WHERE ".
                $signifydConnectCase . ".magento_status<>'complete'"
            );
        } catch (\Exception $e) {
            $this->logger->debug('Update order_id on magento status different from complete failed');
            $this->configWriter->save("signifyd/general/upgrade4.3_inconsistency", "setup");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [

        ];
    }
}
