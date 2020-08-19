<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Signifyd\Connect\Setup\SchemaModification\AddTableColumns;
use Signifyd\Connect\Setup\SchemaModification\AddTables;

/**
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
{
    const TABLE_SIGNIFYD_CONNECT_CASE = 'signifyd_connect_case';

    protected $logger;
    /**
     * @var SchemaModification\AddTables
     */
    private $addTables;
    /**
     * @var SchemaModification\AddTableColumns
     */
    private $addTableColumns;

    public function __construct(
        AddTables $addTables,
        AddTableColumns $addTableColumns,
        \Signifyd\Connect\Logger\Install $logger
    ) {
        $this->logger = $logger;
        $this->addTables = $addTables;
        $this->addTableColumns = $addTableColumns;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $setup->startSetup();

            $this->addTables->execute($setup);

            $this->addTableColumns->execute($setup);

            $this->logger->debug('Installation completed successfully');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
