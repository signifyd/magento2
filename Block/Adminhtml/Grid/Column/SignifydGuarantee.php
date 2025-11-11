<?php

namespace Signifyd\Connect\Block\Adminhtml\Grid\Column;

use Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Model\CasedataFactory;

class SignifydGuarantee extends AbstractRenderer
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * SignifydGuarantee constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param CasedataFactory $casedataFactory
     * @param \Magento\Backend\Block\Context $context
     * @param array $data
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        CasedataFactory $casedataFactory,
        \Magento\Backend\Block\Context $context,
        array $data = []
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->casedataFactory = $casedataFactory;
        parent::__construct($context, $data);
    }

    /**
     * Render method.
     *
     * @param \Magento\Framework\DataObject $row
     * @return string
     */
    public function render(\Magento\Framework\DataObject $row)
    {
        $return = '';
        $quoteId = $row->getData('quote_id');

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataRepository->getByQuoteId($quoteId);

        if ($case->isEmpty() === false) {
            $guarantee = $case->getGuarantee();
            $url = "https://www.signifyd.com/cases/" . $case->getCode();
            $return = "<a href=\"$url\" target=\"_blank\">$guarantee</a>";
        }

        return $return;
    }
}
