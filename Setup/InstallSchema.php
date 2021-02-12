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
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
{
    protected $logger;

    /**
     * @var WriterInterface
     */
    protected $writerInterface;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var DateTime
     */
    protected $dateTime;

    public function __construct(
        \Signifyd\Connect\Logger\Install $logger,
        WriterInterface $writerInterface,
        ScopeConfigInterface $scopeConfigInterface,
        DateTime $dateTime
    ) {
        $this->logger = $logger;
        $this->writerInterface = $writerInterface;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->dateTime = $dateTime;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            if ($this->scopeConfigInterface->isSetFlag('signifyd_connect/general/installation_date') === false) {
                $this->writerInterface->save('signifyd_connect/general/installation_date', $this->dateTime->gmtDate());
            }

            $this->logger->debug('Installation completed successfully');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
