<?php

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\ObserverInterface;
use Signifyd\Connect\Model\Registry;

class CronJob implements ObserverInterface
{
    /**
     * @var Registry
     */
    public $registry;

    /**
     * CronJob constructor.
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $cronJob = $observer->getEvent()->getData('job_name');

        $this->registry->setData('signifyd_cron_job_run', $cronJob);
    }
}
