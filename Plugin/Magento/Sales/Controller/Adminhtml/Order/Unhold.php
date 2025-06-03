<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Controller\Adminhtml\Order;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Backend\Model\Auth\Session;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Helper\OrderHelper;
use Magento\Framework\App\ResourceConnection;
use Signifyd\Connect\Model\UpdateOrder\Action as UpdateOrderAction;

class Unhold
{
    /**
     * @var OrderRepositoryInterface
     */
    public $orderRepository;

    /**
     * @var OrderHelper
     */
    public $orderHelper;

    /**
     * @var Session
     */
    public $authSession;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var ResourceConnection
     */
    public $resourceConnection;

    /**
     * @var UpdateOrderAction
     */
    public $updateOrderAction;

    /**
     * Unhold constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderHelper $orderHelper
     * @param Session $authSession
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param ResourceConnection $resourceConnection
     * @param UpdateOrderAction $updateOrderAction
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderHelper $orderHelper,
        Session $authSession,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ResourceConnection $resourceConnection,
        UpdateOrderAction $updateOrderAction
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderHelper = $orderHelper;
        $this->authSession = $authSession;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->casedataFactory = $casedataFactory;
        $this->resourceConnection = $resourceConnection;
        $this->updateOrderAction = $updateOrderAction;
    }

    /**
     * After execute method.
     *
     * @param \Magento\Sales\Controller\Adminhtml\Order $subject
     * @param mixed $result
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function afterExecute(\Magento\Sales\Controller\Adminhtml\Order $subject, $result)
    {
        $signifydUnhold = $subject->getRequest()->getParam('signifyd_unhold');
        $orderId = $subject->getRequest()->getParam('order_id');

        if (empty($signifydUnhold)) {
            return $result;
        }

        if (empty($orderId)) {
            return $result;
        }

        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderRepository->get($orderId);
            /** @var \Signifyd\Connect\Model\Casedata $case */
            $case = $this->casedataFactory->create();

            $this->orderHelper->addCommentToStatusHistory(
                $order,
                "Signifyd: order status updated by {$this->authSession->getUser()->getUserName()}"
            );

            $this->casedataResourceModel->loadForUpdate($case, $order->getId(), 'order_id', 2);

            if ($this->updateOrderAction->isHoldReleased($case) === false) {
                $case->setEntries('hold_released', 1);
            }

            $this->casedataResourceModel->save($case);
        } catch (\Exception $e) {
            // Triggering case save to unlock case
            if ($case instanceof \Signifyd\Connect\Model\Casedata) {
                $this->casedataResourceModel->save($case);
            }
        }

        return $result;
    }
}
