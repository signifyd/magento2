<?php

namespace Signifyd\Connect\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

class OrderHelper
{
    /**
     * @param Order $order
     * @return string
     */
    public function getCannotHoldReason(Order $order)
    {
        $notHoldableStates = [
            Order::STATE_CANCELED,
            Order::STATE_PAYMENT_REVIEW,
            Order::STATE_COMPLETE,
            Order::STATE_CLOSED,
            Order::STATE_HOLDED
        ];

        if ($order->getState() == Order::STATE_HOLDED) {
            $completeCase = true;
        }

        if (in_array($order->getState(), $notHoldableStates)) {
            $reason = "order is on {$order->getState()} state";
        } elseif ($order->getActionFlag(Order::ACTION_FLAG_HOLD) === false) {
            $reason = "order action flag is set to do not hold";
        } else {
            $reason = "unknown reason";
        }

        return $reason;
    }

    /**
     * @param Order $order
     * @return string
     */
    public function getCannotUnholdReason(Order $order)
    {
        if ($order->getState() != Order::STATE_HOLDED && $order->isPaymentReview() == false) {
            $reason = "order is not holded";
            $completeCase = true;
        } elseif ($order->isPaymentReview()) {
            $reason = 'order is in payment review';
        } elseif ($order->getActionFlag(Order::ACTION_FLAG_UNHOLD) === false) {
            $reason = "order action flag is set to do not unhold";
        } else {
            $reason = "unknown reason";
        }

        return $reason;
    }

    /**
     * @param Order $order
     * @return string
     */
    public function getCannotCancelReason(Order $order)
    {
        $notCancelableStates = [
            Order::STATE_CANCELED,
            Order::STATE_PAYMENT_REVIEW,
            Order::STATE_COMPLETE,
            Order::STATE_CLOSED,
            Order::STATE_HOLDED
        ];

        if (in_array($order->getState(), $notCancelableStates)) {
            $reason = "order is on {$order->getState()} state";
        } elseif (!$order->canReviewPayment() && $order->canFetchPaymentReviewUpdate()) {
            $reason = "conflict with payment review";
        } elseif ($order->getActionFlag(Order::ACTION_FLAG_CANCEL) === false) {
            $reason = "order action flag is set to do not cancel";
        } else {
            $allInvoiced = true;
            foreach ($order->getAllItems() as $item) {
                if ($item->getQtyToInvoice()) {
                    $allInvoiced = false;
                    break;
                }
            }
            if ($allInvoiced) {
                $reason = "all order items are invoiced";
                $completeCase = true;
            } else {
                $reason = "unknown reason";
            }
        }

        return $reason;
    }

    /**
     * @param Order $order
     * @return string
     */
    public function getCannotInvoiceReason(Order $order)
    {
        $notInvoiceableStates = [
            Order::STATE_CANCELED,
            Order::STATE_PAYMENT_REVIEW,
            Order::STATE_COMPLETE,
            Order::STATE_CLOSED,
            Order::STATE_HOLDED
        ];

        if (in_array($order->getState(), $notInvoiceableStates)) {
            $reason = "order is on {$order->getState()} state";
        } elseif ($order->getActionFlag(Order::ACTION_FLAG_INVOICE) === false) {
            $reason = "order action flag is set to do not invoice";
        } else {
            $canInvoiceAny = false;

            foreach ($order->getAllItems() as $item) {
                if ($item->getQtyToInvoice() > 0 && !$item->getLockedDoInvoice()) {
                    $canInvoiceAny = true;
                    break;
                }
            }

            if ($canInvoiceAny) {
                $reason = "unknown reason";
            } else {
                $reason = "no items can be invoiced";
                $completeCase = true;
            }
        }

        return $reason;
    }

    /**
     * @param Invoice $invoice
     * @return bool
     * @throws LocalizedException
     */
    public function isInvoiceValid(Invoice $invoice)
    {
        if ($invoice->isEmpty()) {
            throw new LocalizedException(__('failed to generate invoice'));
        }

        if ($invoice->getTotalQty() == 0) {
            throw new LocalizedException(__('no items found to invoice'));
        }

        return true;
    }
}
