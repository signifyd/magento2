<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Helper;

use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Adyen\Payment\Helper\Webhook as AdyenWebhook;
use Adyen\Payment\Helper\Order as AdyenOrderHelper;

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
     * @var AdyenOrderHelper
     */
    public $adyenOrderHelper;

    /**
     * Cancel constructor.
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param OrderResourceModel $orderResourceModel
     * @param OrderHelper $orderHelper
     * @param AdyenOrderHelper $adyenOrderHelper
     */
    public function __construct(
        Logger $logger,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        OrderResourceModel $orderResourceModel,
        OrderHelper $orderHelper,
        AdyenOrderHelper $adyenOrderHelper
    ) {
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderHelper = $orderHelper;
        $this->adyenOrderHelper = $adyenOrderHelper;
    }

    public function aroundProcessNotification(AdyenWebhook $subject, callable $proceed, $notification)
    {
        try {
            $order = $this->adyenOrderHelper->getOrderByIncrementId($notification->getMerchantReference());

            if (!$order) {
                return $proceed($notification);
            }

            $orderId = $order->getId();
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $orderId, 'order_id');

            if ($case->isEmpty()) {
                return $proceed($notification);;
            }

            $isHoldedBeforeAdyenProcess = $order->canUnhold();

            if ($isHoldedBeforeAdyenProcess) {
                $order->unhold();
                $this->orderResourceModel->save($order);
            }

            $returnValue = $proceed($notification);

            $order = $this->adyenOrderHelper->getOrderByIncrementId($notification->getMerchantReference());

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

        if (isset($returnValue)) {
            return $returnValue;
        } else {
            return $proceed($notification);
        }
    }
}