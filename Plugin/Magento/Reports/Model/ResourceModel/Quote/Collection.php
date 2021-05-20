<?php

namespace Signifyd\Connect\Plugin\Magento\Reports\Model\ResourceModel\Quote;

class Collection
{
    /**
     * In this method Magento overrides the quote_id,
     * so it was necessary to add to the object before the method was executed
     *
     * @param \Magento\Reports\Model\ResourceModel\Quote\Collection $subject
     */
    public function beforeResolveCustomerNames(\Magento\Reports\Model\ResourceModel\Quote\Collection $subject)
    {
        foreach ($subject->getItems() as $item) {
            $item->setData('quote_id', $item->getData('entity_id'));
        }
    }
}
