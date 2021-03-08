<?php

namespace Signifyd\Connect\Block\Adminhtml;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;
use Signifyd\Connect\Model\Casedata;

/**
 * Get Signifyd Case Info
 *
 * @api
 * @since 100.2.0
 */
class CaseInfo extends Template
{
    /**
     * @var Casedata
     */
    private $caseEntity = false;

    /**
     * @var Registry
     */
    private $coreRegistry = false;

    /**
     * CaseInfo constructor.
     * @param Context $context
     * @param Casedata $caseEntity
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        Casedata $caseEntity,
        Registry $registry,
        array $data = []
    ) {
        $this->caseEntity = $caseEntity;
        $this->coreRegistry = $registry;

        parent::__construct($context, $data);
    }

    /**
     * Gets case entity associated with order id.
     *
     * @return Casedata|null
     */
    public function getCaseEntity()
    {
        if ($this->caseEntity->isEmpty()) {
            $order = $this->getOrder();
            if (!$order->isEmpty()) {
                $this->caseEntity = $this->caseEntity->load($order->getId(), 'order_id');
            }
        }

        return $this->caseEntity;
    }

    /**
     * Retrieve order model object
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->coreRegistry->registry('sales_order');
    }

    /**
     * Gets case guarantee disposition status
     */
    public function getCaseGuaranteeDisposition()
    {
        if ($this->getCaseEntity()->getData('guarantee') == "ACCEPT") {
            $labelGuarantee = 'APPROVED';
        } elseif ($this->getCaseEntity()->getData('guarantee') == "REJECT") {
            $labelGuarantee = 'DECLINED';
        } else {
            $labelGuarantee = $this->getCaseEntity()->getData('guarantee');
        }

        return $labelGuarantee;
    }

    /**
     * Gets case score
     */
    public function getCaseScore()
    {
        return floor($this->getCaseEntity()->getData('score'));
    }

    /**
     * @return array|mixed|null
     */
    public function getCheckpointActionReason()
    {
        return $this->getCaseEntity()->getData('checkpoint_action_reason');
    }
}
