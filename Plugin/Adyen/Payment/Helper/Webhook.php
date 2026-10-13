<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Helper;

use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\Payment\AdyenCc\ChargebackDataFactory;
use Signifyd\Connect\Model\Registry;
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
     * @var Registry
     */
    public $registry;

    /**
     * @var ChargebackDataFactory
     */
    public $chargebackDataFactory;

    /**
     * Webhook constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param OrderResourceModel $orderResourceModel
     * @param OrderHelper $orderHelper
     * @param ObjectManagerInterface $objectManagerInterface
     * @param Registry $registry
     * @param ChargebackDataFactory $chargebackDataFactory
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        Logger $logger,
        CasedataFactory $casedataFactory,
        OrderResourceModel $orderResourceModel,
        OrderHelper $orderHelper,
        ObjectManagerInterface $objectManagerInterface,
        Registry $registry,
        ChargebackDataFactory $chargebackDataFactory
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderHelper = $orderHelper;
        $this->objectManagerInterface = $objectManagerInterface;
        $this->registry = $registry;
        $this->chargebackDataFactory = $chargebackDataFactory;
    }

    /**
     * Around process notification method.
     *
     * Makes the event code of the notification being processed available to
     * Signifyd\Connect\Model\Payment\AdyenCc\RecordReturnChecker, which suppresses the Record
     * Return, and leaves the chargeback data of that same notification on the registry, so that
     * Signifyd\Connect\Model\Api\Core\Client reports it instead. The credit memo the Adyen module
     * creates for a chargeback, and therefore both of them, happens entirely inside $proceed.
     *
     * @param AdyenWebhook $subject
     * @param callable $proceed
     * @param mixed $notification
     * @return mixed
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function aroundProcessNotification(AdyenWebhook $subject, callable $proceed, $notification)
    {
        $this->registry->setData('signifyd_notification_event_code', $notification->getEventCode());
        $this->registry->setData(
            Registry::CHARGEBACK_DATA_REGISTRY_KEY,
            ($this->chargebackDataFactory->create())($notification)
        );

        try {
            return $this->doAroundProcessNotification($subject, $proceed, $notification);
        } finally {
            $this->registry->setData('signifyd_notification_event_code');
            $this->registry->setData(Registry::CHARGEBACK_DATA_REGISTRY_KEY);
        }
    }

    /**
     * @param AdyenWebhook $subject
     * @param callable $proceed
     * @param mixed $notification
     * @return mixed
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    private function doAroundProcessNotification(AdyenWebhook $subject, callable $proceed, $notification)
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
