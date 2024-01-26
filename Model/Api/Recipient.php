<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class Recipient
{
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var AddressFactory
     */
    public $addressFactory;

    /**
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param JsonSerializer $jsonSerializer
     * @param AddressFactory $addressFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        JsonSerializer $jsonSerializer,
        AddressFactory $addressFactory
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->jsonSerializer = $jsonSerializer;
        $this->addressFactory = $addressFactory;
    }

    /**
     * Construct a new Recipient object
     * @param $entity Order|Quote
     * @return array
     */
    public function __invoke($entity)
    {
        if ($entity instanceof Order) {
            $shipments = $this->makeRecipient($entity);
        } elseif ($entity instanceof Quote) {
            $shipments = $this->makeRecipientFromQuote($entity);
        } else {
            $shipments = [];
        }

        return $shipments;
    }

    /**
     * @param $order Order
     * @return array
     */
    protected function makeRecipient(Order $order)
    {
        $recipient = [];
        $address = $order->getShippingAddress();

        if ($address !== null) {
            $formatAddress = $this->addressFactory->create();

            $recipient['fullName'] = $address->getName();
            $recipient['organization'] = $address->getCompany();
            $recipient['address'] = $formatAddress($address);
        } else {
            $recipient['email'] = $order->getCustomerEmail();
        }

        if (empty($recipient['fullName'])) {
            $recipient['fullName'] = $order->getCustomerName();
        }

        return $recipient;
    }

    /**
     * @param $quote Quote
     * @return array
     */
    protected function makeRecipientFromQuote(Quote $quote)
    {
        $recipient = [];
        $address = $quote->getShippingAddress()->getCity() !== null ?
            $quote->getShippingAddress() : $quote->getBillingAddress();

        if ($address !== null) {
            $formatAddress = $this->addressFactory->create();

            $recipient['fullName'] = $address->getName();
            $recipient['organization'] = $address->getCompany();
            $recipient['address'] = $formatAddress($address);
        } else {
            $recipient['email'] = $quote->getCustomerEmail();
        }

        if (empty($recipient['fullName'])) {
            $recipient['fullName'] = $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname();
        }

        return $recipient;
    }
}
