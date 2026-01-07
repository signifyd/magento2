<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Customer\Model\Address;
use Magento\Customer\Model\ResourceModel\Address\CollectionFactory as AddressCollectionFactory;

class SavedAddresses
{
    /**
     * @var AddressCollectionFactory
     */
    public $addressCollectionFactory;

    /**
     * @var AddressFactory
     */
    public $addressFactory;

    /**
     * SavedPayments construct.
     *
     * @param AddressCollectionFactory $addressCollectionFactory
     * @param AddressFactory $addressFactory
     */
    public function __construct(
        AddressCollectionFactory $addressCollectionFactory,
        AddressFactory $addressFactory
    ) {
        $this->addressCollectionFactory = $addressCollectionFactory;
        $this->addressFactory = $addressFactory;
    }

    /**
     * Construct a new saved addresses object
     *
     * @param int $customerId
     * @return array
     */
    public function __invoke($customerId)
    {
        /** @var \Magento\Customer\Model\ResourceModel\Address\Collection $addressCollection */
        $addressCollection = $this->addressCollectionFactory->create();
        $addressCollection->addFieldToFilter('parent_id', ['eq' => $customerId]);

        if ($addressCollection->count() == 0) {
            return [];
        }

        $savedAddresses = [];

        foreach ($addressCollection as $address) {
            $savedAddress = ($this->addressFactory->create())($address);
            $isBilling  = $this->isDefaultBilling($address);
            $isShipping = $this->isDefaultShipping($address);

            if ($isBilling && $isShipping) {
                $billingAddress = $savedAddress;
                $billingAddress['addressType'] = 'billing';
                $savedAddresses[] = $billingAddress;

                $shippingAddress = $savedAddress;
                $shippingAddress['addressType'] = 'shipping';
                $savedAddresses[] = $shippingAddress;
            } else {
                if ($isBilling) {
                    $savedAddress['addressType'] = 'billing';
                }
                if ($isShipping) {
                    $savedAddress['addressType'] = 'shipping';
                }

                $savedAddresses[] = $savedAddress;
            }
        }

        return $savedAddresses;
    }

    /**
     * Check if is default billing address.
     *
     * @param Address $address
     * @return bool
     */
    public function isDefaultBilling(Address $address): bool
    {
        return $address->getId() && $address->getId() == $address->getCustomer()->getDefaultBilling()
            || $address->getIsPrimaryBilling()
            || $address->getIsDefaultBilling();
    }

    /**
     * Check if is default shipping address.
     *
     * @param Address $address
     * @return bool
     */
    public function isDefaultShipping(Address $address): bool
    {
        return $address->getId() && $address->getId() == $address->getCustomer()->getDefaultShipping()
            || $address->getIsPrimaryShipping()
            || $address->getIsDefaultShipping();
    }
}
