<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Helper;

use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Adyen\Payment\Helper\Webhook as AdyenWebhook;

class Webhook
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var OrderResourceModel
     */
    public $orderResourceModel;

    /**
     * @var OrderHelper
     */
    public $orderHelper;
    /**
     * @var ObjectManagerInterface
     */
    public $objectManagerInterface;

    /**
     * Cancel constructor.
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param OrderResourceModel $orderResourceModel
     * @param OrderHelper $orderHelper
     * @param ObjectManagerInterface $objectManagerInterface
     */
    public function __construct(
        Logger $logger,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        OrderResourceModel $orderResourceModel,
        OrderHelper $orderHelper,
        ObjectManagerInterface $objectManagerInterface
    ) {
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderHelper = $orderHelper;
        $this->objectManagerInterface = $objectManagerInterface;
    }

    public function aroundProcessNotification(AdyenWebhook $subject, callable $proceed, $notification)
    {
        if (is_null($notification->getMerchantReference())) {
            return $proceed($notification);
        }

        $adyenOrderHelper = $this->objectManagerInterface->create(
            \Adyen\Payment\Helper\Order::class
        );

        $order = $adyenOrderHelper->getOrderByIncrementId($notification->getMerchantReference());

        if (!$order) {
            return $proceed($notification);
        }

        $orderId = $order->getId();
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $orderId, 'order_id');

        if ($case->isEmpty()) {
            $this->casedataResourceModel->save($case);
            return $proceed($notification);
        }

        try {
            $isHoldedBeforeAdyenProcess = $order->canUnhold();

            if ($isHoldedBeforeAdyenProcess) {
                $order->unhold();
                $this->orderResourceModel->save($order);
            }

            $returnValue = $proceed($notification);

            $order = $adyenOrderHelper->getOrderByIncrementId($notification->getMerchantReference());

            //Setting order to hold after adyen process
            if ($isHoldedBeforeAdyenProcess && $order->canHold()) {
                $order->hold();
                $this->orderResourceModel->save($order);
                $this->logger->info(
                    "Hold order {$order->getIncrementId()} after adyen processing",
                    ['entity' => $case]
                );

                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: hold order after adyen processing"
                );
            }
        } catch (\Exception $ex) {
            $context = [];

            if (isset($order) && $order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        } catch (\Error $ex) {
            $context = [];

            if (isset($order) && $order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        }

        $case->setEntries('processed_by_gateway', true);
        $this->casedataResourceModel->save($case);

        if (isset($returnValue)) {
            return $returnValue;
        } else {
            return $proceed($notification);
        }
    }
}
