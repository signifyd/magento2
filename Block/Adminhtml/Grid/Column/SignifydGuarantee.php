<?php

namespace Signifyd\Connect\Block\Adminhtml\Grid\Column;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\CasedataFactory;

class SignifydGuarantee extends AbstractRenderer
{
    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * SignifydGuarantee constructor.
     * @param CasedataResourceModel $casedataResourceModel
     * @param CasedataFactory $casedataFactory
     * @param \Magento\Backend\Block\Context $context
     * @param array $data
     */
    public function __construct(
        CasedataResourceModel $casedataResourceModel,
        CasedataFactory $casedataFactory,
        \Magento\Backend\Block\Context $context,
        array $data = []
    ) {
        $this->casedataResourceModel = $casedataResourceModel;
        $this->casedataFactory = $casedataFactory;
        parent::__construct($context, $data);
    }

    public function render(\Magento\Framework\DataObject $row)
    {
        $return = '';
        $quoteId = $row->getData('quote_id');

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $quoteId, 'quote_id');

        if ($case->isEmpty() === false) {
            $guarantee = $case->getGuarantee();
            $url = "https://www.signifyd.com/cases/" . $case->getCode();
            $return = "<a href=\"$url\" target=\"_blank\">$guarantee</a>";
        }

        return $return;
    }
}
