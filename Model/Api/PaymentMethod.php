<?php

namespace Signifyd\Connect\Model\Api;

use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\MappingVerificationFactory;

class PaymentMethod
{
    /**
     * @var MappingVerificationFactory
     */
    public $mappingVerificationFactory;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * PaymentMethod construct.
     *
     * @param MappingVerificationFactory $mappingVerificationFactory
     * @param Logger $logger
     */
    public function __construct(
        MappingVerificationFactory $mappingVerificationFactory,
        Logger $logger
    ) {
        $this->mappingVerificationFactory = $mappingVerificationFactory;
        $this->logger = $logger;
    }

    /**
     * Construct a new PaymentMethod object
     *
     * @param mixed $entity
     * @return int|mixed|string
     */
    public function __invoke($entity)
    {
        $paymentMethodAdapter = $this->mappingVerificationFactory->createPaymentMethod(
            $entity->getPayment()->getMethod()
        );

        $this->logger->debug('Getting payment method using ' . get_class($paymentMethodAdapter));

        return $paymentMethodAdapter->getData($entity);
    }
}
