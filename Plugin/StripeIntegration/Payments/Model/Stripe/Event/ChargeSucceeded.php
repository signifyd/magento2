<?php

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Model\Stripe\Event;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use StripeIntegration\Payments\Model\Stripe\Event\ChargeSucceeded as StripeChargeSucceeded;

class ChargeSucceeded
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

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
     * ChargeSucceeded constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param OrderResourceModel $orderResourceModel
     * @param OrderHelper $orderHelper
     * @param ObjectManagerInterface $objectManagerInterface
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        Logger $logger,
        CasedataFactory $casedataFactory,
        OrderResourceModel $orderResourceModel,
        OrderHelper $orderHelper,
        ObjectManagerInterface $objectManagerInterface
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderHelper = $orderHelper;
        $this->objectManagerInterface = $objectManagerInterface;
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
            $webhooksHelper = $this->objectManagerInterface->create(
                \StripeIntegration\Payments\Helper\Webhooks::class
            );
            $order = $webhooksHelper->loadOrderFromEvent($arrEvent);
            $case = $this->casedataRepository->getByOrderId($order->getId());

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
                $this->casedataRepository->save($case);
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
