<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class SavedPayments
{
    /**
     * @var OrderCollectionFactory
     */
    public $orderCollectionFactory;

    /**
     * @var PaymentMethodFactory
     */
    public $paymentMethodFactory;

    /**
     * @var CheckoutPaymentDetailsFactory
     */
    public $checkoutPaymentDetailsFactory;

    /**
     * SavedPayments construct.
     *
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param PaymentMethodFactory $paymentMethodFactory
     * @param CheckoutPaymentDetailsFactory $checkoutPaymentDetailsFactory
     */
    public function __construct(
        OrderCollectionFactory $orderCollectionFactory,
        PaymentMethodFactory $paymentMethodFactory,
        CheckoutPaymentDetailsFactory $checkoutPaymentDetailsFactory
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->checkoutPaymentDetailsFactory = $checkoutPaymentDetailsFactory;
    }

    /**
     * Construct a new saved payments object
     *
     * @param int $customerId
     * @return array
     */
    public function __invoke($customerId)
    {
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('customer_id', ['eq' => $customerId]);
        $orderCollection->setOrder('created_at', 'DESC');
        $orderCollection->setPageSize(5);

        if ($orderCollection->count() == 0) {
            return [];
        }

        $savedPayments = [];
        //These fields need to be removed because they do not exist in the savedPayments endpoint.
        $fieldsToRemove = [
            'accountHolderName',
            'cardToken',
            'cardTokenProvider',
            'cardInstallments'
        ];

        foreach ($orderCollection as $order) {
            $payment = $order->getPayment();
            $savedPayment = [];

            if (isset($payment) === false) {
                continue;
            }

            $savedPayment['date'] = $payment->getCreatedAt();
            $savedPayment['paymentMethod'] = ($this->paymentMethodFactory->create())($order);
            $savedPayment['paymentDetails'] = ($this->checkoutPaymentDetailsFactory->create())($order);
            $savedPayment['paymentDetails']['gateway'] = $payment->getMethod();

            foreach ($fieldsToRemove as $field) {
                unset($savedPayment['paymentDetails'][$field]);
            }

            //Renaming key accountHolderTaxId to accountHolderTaxID due to camelCase inconsistency
            // in the savedPayments endpoint.
            if (array_key_exists('accountHolderTaxId', $savedPayment['paymentDetails'])) {
                $savedPayment['paymentDetails']['accountHolderTaxID'] =
                    $savedPayment['paymentDetails']['accountHolderTaxId'];
                unset($savedPayment['paymentDetails']['accountHolderTaxId']);
            }

            //Renaming key accountHolderTaxIdCountry to accountHolderTaxIDCountry due to camelCase inconsistency
            // in the savedPayments endpoint.
            if (array_key_exists('accountHolderTaxIdCountry', $savedPayment['paymentDetails'])) {
                $savedPayment['paymentDetails']['accountHolderTaxIDCountry'] =
                    $savedPayment['paymentDetails']['accountHolderTaxIdCountry'];
                unset($savedPayment['paymentDetails']['accountHolderTaxIdCountry']);
            }

            $savedPayments[] =  $savedPayment;
        }

        return $savedPayments;
    }
}
