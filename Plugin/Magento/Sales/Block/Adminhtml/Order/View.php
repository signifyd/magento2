<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Block\Adminhtml\Order;

use Magento\Sales\Block\Adminhtml\Order\View as MagentoOrderView;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\UpdateOrder\Action as UpdateOrderAction;

class View
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
     * @var UpdateOrderAction
     */
    public $updateOrderAction;

    /**
     * View constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param CasedataFactory $casedataFactory
     * @param UpdateOrderAction $updateOrderAction
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        CasedataFactory $casedataFactory,
        UpdateOrderAction $updateOrderAction
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->casedataFactory = $casedataFactory;
        $this->updateOrderAction = $updateOrderAction;
    }

    /**
     * Before add button method.
     *
     * @param MagentoOrderView $subject
     * @param mixed $buttonId
     * @param array $data
     * @param int $level
     * @param int $sortOrder
     * @param string $region
     * @return array
     */
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
            $case = $this->casedataRepository->getByOrderId($subject->getOrder()->getId());

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
