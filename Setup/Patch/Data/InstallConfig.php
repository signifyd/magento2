<?php

namespace Signifyd\Connect\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class InstallConfig implements DataPatchInterface
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
