<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Controller\Adminhtml\Order;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Backend\Model\Auth\Session;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\OrderHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\RequestInterface;
use Signifyd\Connect\Model\UpdateOrder\Action as UpdateOrderAction;

class Unhold
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

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
     * @var ResourceConnection
     */
    public $resourceConnection;

    /**
     * @var UpdateOrderAction
     */
    public $updateOrderAction;

    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * Unhold constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderHelper $orderHelper
     * @param Session $authSession
     * @param ResourceConnection $resourceConnection
     * @param UpdateOrderAction $updateOrderAction
     * @param RequestInterface $request
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        OrderRepositoryInterface $orderRepository,
        OrderHelper $orderHelper,
        Session $authSession,
        ResourceConnection $resourceConnection,
        UpdateOrderAction $updateOrderAction,
        RequestInterface $request
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->orderRepository = $orderRepository;
        $this->orderHelper = $orderHelper;
        $this->authSession = $authSession;
        $this->resourceConnection = $resourceConnection;
        $this->updateOrderAction = $updateOrderAction;
        $this->request = $request;
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
        $signifydUnhold = $this->request->getParam('signifyd_unhold');
        $orderId = $this->request->getParam('order_id');

        if (empty($signifydUnhold)) {
            return $result;
        }

        if (empty($orderId)) {
            return $result;
        }

        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderRepository->get($orderId);

            $this->orderHelper->addCommentToStatusHistory(
                $order,
                "Signifyd: order status updated by {$this->authSession->getUser()->getUserName()}"
            );

            $case = $this->casedataRepository->getForUpdate($order->getId(), 'order_id', 2);

            if ($this->updateOrderAction->isHoldReleased($case) === false) {
                $case->setEntries('hold_released', 1);
            }

            $this->casedataRepository->save($case);
        } catch (\Exception $e) {
            // Triggering case save to unlock case
            if ($case instanceof \Signifyd\Connect\Model\Casedata) {
                $this->casedataRepository->save($case);
            }
        }

        return $result;
    }
}
