<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Block\Adminhtml\Order;

use Magento\Sales\Block\Adminhtml\Order\View as MagentoOrderView;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\UpdateOrder\Action as UpdateOrderAction;

class View
{
    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var UpdateOrderAction
     */
    public $updateOrderAction;

    /**
     * View constructor.
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param UpdateOrderAction $updateOrderAction
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        UpdateOrderAction $updateOrderAction
    ) {
        $this->casedataResourceModel = $casedataResourceModel;
        $this->casedataFactory = $casedataFactory;
        $this->updateOrderAction = $updateOrderAction;
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
            /** @var \Signifyd\Connect\Model\Casedata $case */
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $subject->getOrder()->getId(), 'order_id');

            if (!$case->isEmpty()) {
                $guarantee = $case->getData('guarantee');

                if (!$this->updateOrderAction->isHoldReleased($case) && $guarantee == 'N/A') {
                    $url = $subject->getUrl('sales/*/unhold', ['signifyd_unhold' => 1]);

                    $data['class'] = $data['class'] . ' confirm-unhold';
                    $data['data_attribute']['url'] = $url;
                }
            }
        }

        return [$buttonId, $data, $level, $sortOrder, $region];
    }
}
