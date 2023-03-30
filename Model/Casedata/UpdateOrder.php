<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\Casedata;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\Casedata\UpdateOrder\HoldFactory;
use Signifyd\Connect\Model\Casedata\UpdateOrder\UnholdFactory;
use Signifyd\Connect\Model\Casedata\UpdateOrder\CancelFactory;
use Signifyd\Connect\Model\Casedata\UpdateOrder\CaptureFactory;
use Signifyd\Connect\Model\Casedata\UpdateOrder\RefundFactory;

/**
 * Defines link data for the comment field in the config page
 */
class UpdateOrder
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var HoldFactory
     */
    protected $holdFactory;

    /**
     * @var UnholdFactory
     */
    protected $unholdFactory;

    /**
     * @var CancelFactory
     */
    protected $cancelFactory;

    /**
     * @var CaptureFactory
     */
    protected $captureFactory;

    /**
     * @var RefundFactory
     */
    protected $refundFactory;

    /**
     * @param ConfigHelper $configHelper
     * @param OrderHelper $orderHelper
     * @param Logger $logger
     * @param SerializerInterface $serializer
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param OrderResourceModel $orderResourceModel
     * @param HoldFactory $holdFactory
     * @param UnholdFactory $unholdFactory
     * @param CancelFactory $cancelFactory
     * @param CaptureFactory $captureFactory
     * @param RefundFactory $refundFactory
     */
    public function __construct(
        ConfigHelper $configHelper,
        OrderHelper $orderHelper,
        Logger $logger,
        SerializerInterface $serializer,
        ScopeConfigInterface $scopeConfigInterface,
        OrderResourceModel $orderResourceModel,
        HoldFactory $holdFactory,
        UnholdFactory $unholdFactory,
        CancelFactory $cancelFactory,
        CaptureFactory $captureFactory,
        RefundFactory $refundFactory
    ) {
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->orderResourceModel = $orderResourceModel;
        $this->holdFactory = $holdFactory;
        $this->unholdFactory = $unholdFactory;
        $this->cancelFactory = $cancelFactory;
        $this->captureFactory = $captureFactory;
        $this->refundFactory = $refundFactory;
    }

    public function __invoke($case)
    {
        $orderAction = $this->handleGuaranteeChange($case);

        $this->logger->debug(
            $message = "Updating order with action: " . $this->serializer->serialize($orderAction),
            ['entity' => $case]
        );

        $enableTransaction = $this->scopeConfigInterface->isSetFlag('signifyd/general/enable_transaction');
        $loadForUpdate = false;

        if ($enableTransaction) {
            $this->logger->info("Begin database transaction");
            $this->orderResourceModel->getConnection()->beginTransaction();
            $loadForUpdate = true;
        }

        try {
            $order = $case->getOrder(true, $loadForUpdate);
            $completeCase = false;

            if (in_array($order->getState(), [Order::STATE_CANCELED, Order::STATE_COMPLETE, Order::STATE_CLOSED])) {
                $orderAction["action"] = 'nothing';
            }

            // When Async e-mail sending it is enabled, do not process the order until the e-mail is sent
            $isAsyncEmailEnabled = $this->configHelper->getConfigData(
                'sales_email/general/async_sending',
                $order,
                true
            );

            if ($isAsyncEmailEnabled && $order->getData('send_email') == 1 && empty($order->getEmailSent())) {
                $case->setEntries('fail', 1);
                $orderAction['action'] = false;

                $message = "Will not process order {$order->getIncrementId()} because async e-mail has not been sent";
                $this->logger->debug($message);
            }

            $storeId = $order->getStoreId();

            $enabledConfig = $this->scopeConfigInterface->getValue(
                'signifyd/general/enabled',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $storeId
            );

            $isPassive = $enabledConfig == 'passive';

            if ($isPassive && $orderAction['action'] !== false) {
                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: order action {$orderAction['action']}",
                    false,
                    $isPassive
                );

                $orderAction['action'] = false;
                $completeCase = true;
            }

            switch ($orderAction["action"]) {
                case "hold":
                    $hold = $this->holdFactory->create();
                    $completeCase =  $hold($order, $case, $orderAction, $completeCase);
                    break;

                case "unhold":
                    $unhold = $this->unholdFactory->create();
                    $completeCase =  $unhold($order, $case, $orderAction, $completeCase);
                    break;

                case "cancel":
                    $cancel = $this->cancelFactory->create();
                    $completeCase =  $cancel($order, $case, $orderAction, $completeCase);
                    break;

                case "capture":
                    $capture = $this->captureFactory->create();
                    $completeCase =  $capture($order, $case, $enableTransaction, $completeCase);
                    break;

                case "refund":
                    $refund = $this->refundFactory->create();
                    $completeCase =  $refund($order, $case, $completeCase);
                    break;

                // Do nothing, but do not complete the case on Magento side
                // This action should be used when something is processing on Signifyd end and extension should wait
                // E.g.: Signifyd returns guarantee disposition PENDING because case it is on manual review
                case 'wait':
                    break;

                // Nothing is an action from Signifyd workflow, different from when no action is given (null or empty)
                // If workflow is set to do nothing, so complete the case
                case 'nothing':
                    $completeCase = true;
                    break;
            }

            if ($completeCase) {
                $case->setMagentoStatus(Casedata::COMPLETED_STATUS)
                    ->setUpdated();
            }

            if ($enableTransaction) {
                $this->orderResourceModel->getConnection()->commit();
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());

            if ($enableTransaction) {
                $this->orderResourceModel->getConnection()->rollBack();
            }

            return false;
        }
    }

    /**
     * @param $guaranteeDisposition
     * @return array|string[]
     */
    protected function handleGuaranteeChange($case)
    {
        $requestGuarantee = $case->getOrigData('guarantee');
        $caseGuarantee = $case->getData('guarantee');
        /** @var \Magento\Sales\Model\Order $order */
        $order = $case->getOrder(true);

        // Reviewed Cases
        if (($requestGuarantee == 'REJECT' || $requestGuarantee == 'DECLINED') &&
            $requestGuarantee != $caseGuarantee &&
            $order->getState() === Order::STATE_CANCELED
        ) {
            return ["action" => 'nothing', "reason" => 'declined guarantees reviewed to approved'];
        }

        switch ($case->getGuarantee()) {
            case "REJECT":
            case "DECLINED":
                $result = ["action" => $case->getNegativeAction(), "reason" => "guarantee declined"];
                break;

            case 'ACCEPT':
            case "APPROVED":
                $result = ["action" => $case->getPositiveAction(), "reason" => "guarantee approved"];
                break;

            case 'PENDING':
                $result = ["action" => 'wait', "reason" => 'case in manual review'];
                break;

            default:
                $result = ["action" => '', "reason" => ''];
        }

        $this->logger->debug("Action for {$case->getOrderIncrement()}: {$result['action']}", ['entity' => $case]);

        return $result;
    }
}