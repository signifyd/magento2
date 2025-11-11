<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Helper;

use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Model\CasedataFactory;
use Adyen\Payment\Helper\Webhook as AdyenWebhook;

class Webhook
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
     * Webhook constructor.
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
     * Around process notification method.
     *
     * @param AdyenWebhook $subject
     * @param callable $proceed
     * @param mixed $notification
     * @return mixed
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function aroundProcessNotification(AdyenWebhook $subject, callable $proceed, $notification)
    {
        if ($notification->getMerchantReference() === null) {
            return $proceed($notification);
        }

        $adyenOrderHelper = $this->objectManagerInterface->create(
            \Adyen\Payment\Helper\Order::class
        );

        $order = $adyenOrderHelper->getOrderByIncrementId($notification->getMerchantReference());

        if (!$order) {
            return $proceed($notification);
        }

        $case = $this->casedataRepository->getByOrderId($order->getId());

        if ($case->isEmpty()) {
            $this->casedataRepository->save($case);
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
        $this->casedataRepository->save($case);

        if (isset($returnValue)) {
            return $returnValue;
        } else {
            return $proceed($notification);
        }
    }
}
