<?php

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;

class CronJob implements ObserverInterface
{
    /**
     * @var Registry
     */
    protected $registry;

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
        $cronJobRegistry = $this->registry->registry('signifyd_cron_job_run');

        if (isset($cronJobRegistry)) {
            $this->registry->unregister('signifyd_cron_job_run');
        }

        $this->registry->register('signifyd_cron_job_run', $cronJob);
    }
}
