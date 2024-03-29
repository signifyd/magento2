<?php

namespace Signifyd\Connect\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class InstallConfig implements DataPatchInterface
{
    public $logger;

    /**
     * @var WriterInterface
     */
    public $writerInterface;

    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * @var DateTime
     */
    public $dateTime;

    /**
     * @param \Signifyd\Connect\Logger\Install $logger
     * @param WriterInterface $writerInterface
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param DateTime $dateTime
     */
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
     * @return $this|InstallConfig
     */
    public function apply()
    {
        try {
            if ($this->scopeConfigInterface->isSetFlag('signifyd_connect/general/installation_date') === false) {
                $this->writerInterface->save('signifyd_connect/general/installation_date', $this->dateTime->gmtDate());
            }

            $this->logger->debug('Installation completed successfully');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return $this;
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
