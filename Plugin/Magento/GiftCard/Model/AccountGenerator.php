<?php

namespace Signifyd\Connect\Plugin\Magento\GiftCard\Model;

use Magento\GiftCard\Model\AccountGenerator as MagentoAccountGenerator;
use Magento\Sales\Model\Order\Item as OrderItem;

class AccountGenerator
{
    /**
     * Do not allow to generate more gift cards than purchased
     *
     * @param MagentoAccountGenerator $subject
     * @param OrderItem $orderItem
     * @param int $qty
     * @param array $options
     * @return array
     */
    public function beforeGenerate(MagentoAccountGenerator $subject, OrderItem $orderItem, int $qty, array $options)
    {
        $giftcardCreatedCodes = $orderItem->getProductOptionByCode('giftcard_created_codes');
        $giftcardCodesCount = is_array($giftcardCreatedCodes) ? count($giftcardCreatedCodes) : 0;
        $orderItemQty = $orderItem->getQtyOrdered() - $orderItem->getQtyCanceled() - $orderItem->getQtyRefunded();

        if ($qty > $orderItemQty - $giftcardCodesCount) {
            $qty = $orderItemQty - $giftcardCodesCount;
        }

        return [$orderItem, $qty, $options];
    }
}
