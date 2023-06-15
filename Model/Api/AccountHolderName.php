<?php

namespace Signifyd\Connect\Model\Api;

use Braintree\Exception;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\PaymentVerificationFactory;

class AccountHolderName
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var PaymentVerificationFactory
     */
    protected $paymentVerificationFactory;

    /**
     * @param Logger $logger
     * @param PaymentVerificationFactory $paymentVerificationFactory
     */
    public function __construct(
        Logger $logger,
        PaymentVerificationFactory $paymentVerificationFactory
    ) {
        $this->logger = $logger;
        $this->paymentVerificationFactory = $paymentVerificationFactory;
    }

    /**
     * Construct a new AccountHolderName object
     * @param $entity Order|Quote
     * @return array
     */
    public function __invoke($entity)
    {
        if ($entity instanceof Order) {
            $accountHolder = $this->getCardholder($entity);
        } elseif ($entity instanceof Quote) {
            $accountHolder = $this->getCardholderFromQuote($entity);
        } else {
            $accountHolder = [];
        }

        return $accountHolder;
    }

    /**
     * @param Quote $quote
     * @return array|string|string[]|null
     */
    protected function getCardholderFromQuote(Quote $quote)
    {
        try {
            $firstname = $quote->getBillingAddress()->getFirstname();
            $lastname = $quote->getBillingAddress()->getLastname();
            $cardholder = trim($firstname) . ' ' . trim($lastname);
            $cardholder = strtoupper($cardholder);
            $cardholder = preg_replace('/  +/', ' ', $cardholder);

            return $cardholder;
        } catch (Exception $e) {
            $this->logger->error('Error fetching cardholder: ' . $e->getMessage(), ['entity' => $quote]);
            return '';
        }
    }

    /**
     * Gets cardholder for order
     *
     * @param Order $order
     * @return string
     */
    protected function getCardholder(Order $order)
    {
        try {
            $paymentMethod = $order->getPayment()->getMethod();
            $cardholderAdapter = $this->paymentVerificationFactory->createPaymentCardholder($paymentMethod);

            $this->logger->debug(
                'Getting card holder using ' . get_class($cardholderAdapter),
                ['entity' => $order]
            );

            $cardholder = $cardholderAdapter->getData($order);

            if (empty($cardholder) || !mb_check_encoding($cardholder, 'UTF-8') || strpos($cardholder, '?') !== false) {
                $firstname = $order->getBillingAddress()->getFirstname();
                $lastname = $order->getBillingAddress()->getLastname();
                $cardholder = trim($firstname) . ' ' . trim($lastname);
            }

            $cardholder = strtoupper($cardholder);

            $cardholder = preg_replace('/  +/', ' ', $cardholder);

            return $cardholder;
        } catch (Exception $e) {
            $this->logger->error(
                'Error fetching cardholder: ' . $e->getMessage(),
                ['entity' => $order]
            );
            return '';
        }
    }
}
