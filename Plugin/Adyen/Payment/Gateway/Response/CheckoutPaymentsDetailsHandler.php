<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Gateway\Response;

use Adyen\Payment\Gateway\Response\CheckoutPaymentsDetailsHandler as AdyenCheckoutPaymentsDetailsHandler;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\Casedata;

class CheckoutPaymentsDetailsHandler
{
    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * CheckoutPaymentsDetailsHandler constructor.
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param Logger $logger
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->logger = $logger;
    }

    public function beforeHandle(AdyenCheckoutPaymentsDetailsHandler $subject, array $handlingSubject, array $response)
    {
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);
        /** @var \Magento\Sales\Model\Order $order */
        $order = $paymentDataObject->getPayment()->getOrder();
        $quoteId = $order->getQuoteId();

        if (isset($response['additionalData']['signifydInvestigationId'])) {
            /** @var $case \Signifyd\Connect\Model\Casedata */
            $case = $this->casedataFactory->create();
            $case->setCode($response['additionalData']['signifydInvestigationId']);
            $case->setMagentoStatus(Casedata::IN_REVIEW_STATUS);
            $case->setQuoteId($quoteId);
            $case->setEntriesText("");
            $case->setCreated(date('Y-m-d H:i:s', time()));
            $case->setUpdated();
            $case->setPolicyName(Casedata::POST_AUTH);
            $this->casedataResourceModel->save($case);
        }
    }
}
