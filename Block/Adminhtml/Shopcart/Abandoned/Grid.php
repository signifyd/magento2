<?php

namespace Signifyd\Connect\Block\Adminhtml\Shopcart\Abandoned;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Store\Model\ScopeInterface;
use Signifyd\Connect\Helper\PurchaseHelper;

class Grid extends \Magento\Reports\Block\Adminhtml\Shopcart\Abandoned\Grid
{
    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * Grid constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Backend\Helper\Data $backendHelper
     * @param \Magento\Reports\Model\ResourceModel\Quote\CollectionFactory $quotesFactory
     * @param PurchaseHelper $purchaseHelper
     * @param JsonSerializer $jsonSerializer
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Magento\Reports\Model\ResourceModel\Quote\CollectionFactory $quotesFactory,
        PurchaseHelper $purchaseHelper,
        JsonSerializer $jsonSerializer,
        array $data = []
    ) {
        $this->purchaseHelper = $purchaseHelper;
        $this->jsonSerializer = $jsonSerializer;
        parent::__construct($context, $backendHelper, $quotesFactory, $data);
    }

    /**
     * Prepare columns
     *
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _prepareColumns()
    {
        $return = parent::_prepareColumns();

        if ($this->getRequest()->getParam('store')) {
            $storeId = (int)$this->getRequest()->getParam('store');
            $policyName = $this->purchaseHelper->getPolicyName(ScopeInterface::SCOPE_STORES, $storeId);
        } else {
            $policyName = $this->purchaseHelper->getPolicyName();
        }

        $isPreAuthInUse = $this->purchaseHelper->getIsPreAuthInUse($policyName);

        if ($isPreAuthInUse) {
            $this->addColumn(
                'signifyd_guarantee',
                [
                    'header' => __('Signifyd Decision'),
                    'index' => 'signifyd_guarantee',
                    'sortable' => false,
                    'header_css_class' => 'col-name',
                    'column_css_class' => 'col-name',
                    'renderer' => \Signifyd\Connect\Block\Adminhtml\Grid\Column\SignifydGuarantee::class
                ]
            );
        }

        return $return;
    }
}
