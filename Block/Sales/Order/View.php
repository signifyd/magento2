<?php

namespace Signifyd\Connect\Block\Sales\Order;

use Magento\Framework\Registry;

/**
 * Class View
 * @package Signifyd\Connect\Block\Sales\Order
 * @author Eddie Spradley <espradley@gmail.com>
 */
class View extends \Magento\Framework\View\Element\Template
{

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry = null;

    /**
     * View constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        Registry $registry,
        array $data = []
    )
    {
        $this->coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve current order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->coreRegistry->registry('current_order');
    }

    /**
     * @return string
     */
    public function getSignifydLink()
    {
        return 'https://www.signifyd.com/cases/'.$this->getOrder()->getSignifydCode();
    }

    /**
     * @return string
     */
    public function getSignifydCode()
    {
        return ($this->getOrder()->getSignifydCode() ? $this->getOrder()->getSignifydCode() : "Pending");
    }

    /**
     * @return mixed
     */
    public function getSignifydGuarantee()
    {
        return $this->getOrder()->getSignifydGuarantee();
    }

    /**
     * @return mixed
     */
    public function getSignifydScore()
    {
        return $this->getOrder()->getSignifydScore();
    }

}
