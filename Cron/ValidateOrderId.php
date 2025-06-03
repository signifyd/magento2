<?php

/**
 * Copyright 2016 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Cron;

use Signifyd\Connect\Model\ResourceModel\Casedata\CollectionFactory as CasedataCollectionFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ValidateOrderId
{
    /**
     * @var CasedataCollectionFactory
     */
    public $casedataCollection;

    /**
     * @var WriterInterface
     */
    public $writerInterface;

    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * ValidateOrderId construct.
     *
     * @param CasedataCollectionFactory $casedataCollection
     * @param WriterInterface $writerInterface
     * @param ScopeConfigInterface $scopeConfigInterface
     */
    public function __construct(
        CasedataCollectionFactory $casedataCollection,
        WriterInterface $writerInterface,
        ScopeConfigInterface $scopeConfigInterface
    ) {
        $this->casedataCollection = $casedataCollection;
        $this->writerInterface = $writerInterface;
        $this->scopeConfigInterface = $scopeConfigInterface;
    }

    /**
     * Execute method.
     *
     * @return void
     */
    public function execute()
    {
        $upgradeInconsistency = $this->scopeConfigInterface->getValue("signifyd/general/upgrade4.3_inconsistency");

        if (isset($upgradeInconsistency)) {
            return;
        }

        /** @var \Signifyd\Connect\Model\ResourceModel\Casedata\Collection $casedataCollection */
        $casedataCollection = $this->casedataCollection->create();
        $casedataCollection->addFieldToFilter('order_id', ['null' => true]);

        if ($casedataCollection->count() > 0) {
            $this->writerInterface->save("signifyd/general/upgrade4.3_inconsistency", "cron");
        } else {
            $this->writerInterface->save("signifyd/general/upgrade4.3_inconsistency", "fixed");
        }
    }
}
