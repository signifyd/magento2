<?php

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Model\Stripe\Event;

use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use StripeIntegration\Payments\Model\Stripe\Event\ChargeSucceeded as StripeChargeSucceeded;
use StripeIntegration\Payments\Helper\Webhooks;

class ChargeSucceeded
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
     * @var Webhooks
     */
    public $webhooksHelper;

    /**
     * ChargeSucceeded constructor.
     *
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param OrderResourceModel $orderResourceModel
     * @param OrderHelper $orderHelper
     * @param Webhooks $webhooksHelper
     */
    public function __construct(
        Logger $logger,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        OrderResourceModel $orderResourceModel,
        OrderHelper $orderHelper,
        Webhooks $webhooksHelper
    ) {
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderHelper = $orderHelper;
        $this->webhooksHelper = $webhooksHelper;
    }

    /**
     * Plugin after on process method.
     *
     * @param StripeChargeSucceeded $subject
     * @param $arrEvent
     * @param $object
     * @return void
     */
    public function afterProcess(
        StripeChargeSucceeded $subject,
        $result,
        $arrEvent,
        $object
    ) {
        try {
            $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);
            $orderId = $order->getId();
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $orderId, 'order_id');

            if ($case->isEmpty()) {
                return $result;
            }

            //Setting order to hold after stripe Charge
            if ($case->getEntries('is_holded') == 1 && $order->canHold()) {
                $order->hold();
                $this->orderResourceModel->save($order);
                $this->logger->info(
                    "Hold order {$order->getIncrementId()} after stripe Charge",
                    ['entity' => $case]
                );

                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: hold order after stripe Charge"
                );

                $case->unsetEntries('is_holded');
                $this->casedataResourceModel->save($case);
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
    }
}