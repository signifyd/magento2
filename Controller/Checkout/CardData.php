<?php

namespace Signifyd\Connect\Controller\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as RequestHttp;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\PreAuth\Base\Handler as BaseHandler;

/**
 * Stores the payment details collected on checkouts which do not use the payment information endpoint
 */
class CardData implements HttpPostActionInterface
{
    /**
     * Payment details the checkout is allowed to store on the quote payment
     */
    public const ALLOWED_CARD_DETAILS = [
        'cardBin',
        'cardLast4',
        'cardExpiryMonth',
        'cardExpiryYear',
        'holderName',
        'storedPaymentMethodId'
    ];

    /**
     * @var RequestHttp
     */
    public $request;

    /**
     * @var JsonFactory
     */
    public $resultJsonFactory;

    /**
     * @var Session
     */
    public $checkoutSession;

    /**
     * @var QuoteResourceModel
     */
    public $quoteResourceModel;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * CardData constructor.
     *
     * @param RequestHttp $request
     * @param JsonFactory $resultJsonFactory
     * @param Session $checkoutSession
     * @param QuoteResourceModel $quoteResourceModel
     * @param Logger $logger
     */
    public function __construct(
        RequestHttp $request,
        JsonFactory $resultJsonFactory,
        Session $checkoutSession,
        QuoteResourceModel $quoteResourceModel,
        Logger $logger
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->logger = $logger;
    }

    /**
     * Store the posted payment details on the quote payment
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $storedDetails = [];

        try {
            $cardDetails = (array) $this->request->getPostValue();
            $receivedDetails = array_intersect(array_keys($cardDetails), self::ALLOWED_CARD_DETAILS);

            // Only the keys are logged, the values are payment data
            $this->logger->info(
                'Checkout payment details received: ' .
                (empty($receivedDetails) ? 'none' : implode(', ', $receivedDetails))
            );

            $quote = $this->checkoutSession->getQuote();

            // A late call after the order is placed would store the card on the next quote
            if ((int)$quote->getItemsCount() === 0) {
                $this->logger->info('Checkout payment details discarded, there is no active cart');

                return $result->setData(['stored' => false]);
            }

            $payment = $quote->getPayment();
            $paymentMethod = (string)$payment->getMethod();

            // Data collected before the shopper switched to another payment method
            if (isset($cardDetails['paymentMethod']) && $cardDetails['paymentMethod'] !== $paymentMethod) {
                $this->logger->info('Checkout payment details discarded, the payment method has changed');

                return $result->setData(['stored' => false]);
            }

            if ($payment->getAdditionalInformation(BaseHandler::PAYMENT_METHOD_KEY) !== $paymentMethod) {
                foreach (self::ALLOWED_CARD_DETAILS as $key) {
                    $payment->unsAdditionalInformation($key);
                }
            }

            foreach (self::ALLOWED_CARD_DETAILS as $key) {
                if (isset($cardDetails[$key]) === false
                    || is_scalar($cardDetails[$key]) === false
                    || (string)$cardDetails[$key] === '') {
                    continue;
                }

                $payment->setAdditionalInformation($key, (string)$cardDetails[$key]);
                $storedDetails[] = $key;
            }

            if (empty($storedDetails)) {
                return $result->setData(['stored' => false]);
            }

            $payment->setAdditionalInformation(BaseHandler::PAYMENT_METHOD_KEY, $paymentMethod);
            $this->quoteResourceModel->save($quote);

            $this->logger->info(
                'Checkout payment details stored on quote ' . $quote->getId() . ': ' .
                implode(', ', $storedDetails)
            );
        } catch (\Exception $e) {
            $this->logger->error('Could not store the checkout payment details: ' . $e->getMessage());

            return $result->setData(['stored' => false]);
        }

        return $result->setData(['stored' => true]);
    }
}
