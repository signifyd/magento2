<?php

namespace Signifyd\Connect\Cron;

use Exception;
use Magento\Sales\Model\OrderFactory;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Helper\Retry;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\Payment\Adyen\Mapper as AdyenMapper;
use Signifyd\Connect\Model\Payment\Cybersorurce\Mapper;

class AsyncJob
{
    /**
     * @var Retry
     */
    public $caseRetryObj;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var PurchaseHelper
     */
    private $helper;
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var Casedata
     */
    private $caseData;
    /**
     * @var Mapper
     */
    private $cybersource;
    /**
     * @var AdyenMapper
     */
    private $adyen_cc;

    public function __construct(
        PurchaseHelper $helper,
        Logger $logger,
        Retry $caseRetryObj,
        Casedata $caseData,
        OrderFactory $orderFactory,
        Mapper $cybersource,
        AdyenMapper $adyen
    ) {
        $this->caseRetryObj = $caseRetryObj;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->orderFactory = $orderFactory;
        $this->caseData = $caseData;
        $this->cybersource = $cybersource;
        $this->adyen_cc = $adyen;
    }

    public function execute()
    {
        /**
         * Getting all the cases that were not submitted to Signifyd
         */
        $waitingCases = $this->getAsyncWaitingCases(Casedata::ASYNC_WAIT);

        foreach ($waitingCases as $case) {
            $message = "Signifyd: preparing for send case no: {$case['order_increment']}";
            $this->logger->debug($message, ['entity' => $case]);
            $caseObj = $this->caseData->load($case->getOrderIncrement());
            $order = $this->getOrder($case['order_increment']);
            $retries = (int)$caseObj->getData('retries') + 1;
            $data = $this->checkData($order, $retries);
            $caseResponse = false;

            if (false !== $data) {
                $caseData = $this->helper->processOrderData($order);
                $this->addData($caseData, $data);
                $caseResponse = $this->helper->postCaseToSignifyd($caseData, $order);
                $caseObj->setMagentoStatus(Casedata::IN_REVIEW_STATUS);
                $retries = 0;
            } else {
                if ($case->getRetries() == 5) {
                    $caseData = $this->helper->processOrderData($order);
                    $caseResponse = $this->helper->postCaseToSignifyd($caseData, $order);
                    $caseObj->setMagentoStatus(Casedata::IN_REVIEW_STATUS);
                    $retries = 0;
                } else {
                    $this->logger->debug("Case not sent because no data yet");
                }
            }

            if (is_object($caseResponse)) {
                $caseObj->setCode($caseResponse->getCaseId());
            }

            $caseObj->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
            $caseObj->setRetries($retries);
            $caseObj->save();
        }
    }

    /**
     * @param $incrementId
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder($incrementId)
    {
        return $this->orderFactory->create()->loadByIncrementId($incrementId);
    }

    /**
     * @param $status
     * @return array
     */
    public function getAsyncWaitingCases($status)
    {
        $casesCollection = $this->caseData->getCollection();
        $casesCollection->addFieldToFilter('magento_status', ['eq' => $status]);
        $casesCollection->addFieldToFilter('retries', ['lt' => 6]);
        $casesToRetry = [];

        foreach ($casesCollection->getItems() as $case) {
            $casesToRetry[$case->getId()] = $case;
        }

        return $casesToRetry;
    }

    public function checkData($order, $retries)
    {
        $orderPayment = $order->getPayment();
        $paymentMethod = $orderPayment->getMethod();
        try {
            $data = $this->{strtolower($paymentMethod)}->getData($order, $retries);
        } catch (Exception $ex) {
            $this->logger->error($ex->__toString());
            $data = false;
        }

        return $data;
    }

    public function addData($case, $data)
    {
        $case->card->bin = $data['cc_number'];
        $case->card->last4 = $data['cc_last_4'];
        $case->card->expiryMonth = $data['cc_exp_month'];
        $case->card->expiryYear = $data['cc_exp_year'];
        $case->card->hash = $data['cc_trans_id'];
        $case->purchase->avsResponseCode = $data['cc_avs_status'];
        $case->purchase->cvvResponseCode = $data['cc_cvv_status'];

        return true;
    }
}
