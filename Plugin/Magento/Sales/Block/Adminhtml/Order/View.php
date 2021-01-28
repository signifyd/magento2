<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Block\Adminhtml\Order;

use Magento\Sales\Block\Adminhtml\Order\View as MagentoOrderView;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;

class View
{
    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * View constructor.
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel
    ) {
        $this->casedataResourceModel = $casedataResourceModel;
        $this->casedataFactory = $casedataFactory;
    }

    public function beforeAddButton(
        MagentoOrderView $subject,
        $buttonId,
        $data,
        $level = 0,
        $sortOrder = 0,
        $region = 'toolbar'
    ) {
        if ($buttonId == 'order_unhold') {
            /** @var $case \Signifyd\Connect\Model\Casedata */
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $subject->getOrder()->getId(), 'order_id');

            if (!$case->isEmpty()) {
                $guarantee = $case->getData('guarantee');

                if (!$case->isHoldReleased() && $guarantee == 'N/A') {
                    $url = $subject->getUrl('sales/*/unhold', ['signifyd_unhold' => 1]);

                    $data['class'] = $data['class'] . ' confirm-unhold';
                    $data['data_attribute']['url'] = $url;
                }
            }
        }

        return [$buttonId, $data, $level, $sortOrder, $region];
    }
}
