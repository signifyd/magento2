<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Helper\LogHelper;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Model\CaseRetry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\RequestInterface;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Purchase implements ObserverInterface
{
    /**
     * @var \Signifyd\Connect\Helper\LogHelper
     */
    protected $logger;

    /**
     * @var \Signifyd\Connect\Helper\PurchaseHelper
     */
    protected $helper;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManagerInterface;

    /**
     * Methods that should wait e-mail sent to hold order
     * @var array
     */
    protected $specialMethods = ['payflow_express'];

    /**
     * List of methods that uses a different event for triggering case creation
     * This is useful when it's needed case creation to be delayed to wait for other processes like data return from
     * payment method
     *
     * @var array
     */
    protected $ownEventsMethods = ['authorizenet_directpost'];

    /**
     * Restricted payment methods
     * @var array
     * @deprecated
     *
     * Restricted payment methods are no longer managed in this method.
     * Please add customized restricted payment methods to core_config_data table as below.
     *
     * INSERT INTO core_config_data(path, value) VALUES (
     * 'signifyd/general/restrict_payment_methods',
     * 'checkmo,cashondelivery,banktransfer,purchaseorder'
     * );
     */
    protected $restrictedMethods;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * Purchase constructor.
     * @param LogHelper $logger
     * @param PurchaseHelper $helper
     * @param ConfigHelper $configHelper
     * @param ObjectManagerInterface $objectManagerInterface
     * @param StoreManagerInterface|null $storeManager
     */
    public function __construct(
        LogHelper $logger,
        PurchaseHelper $helper,
        ConfigHelper $configHelper,
        ObjectManagerInterface $objectManagerInterface,
        StoreManagerInterface $storeManager = null,
        RequestInterface $request
    ) {
        $this->logger = $logger;
        $this->helper = $helper;
        $this->configHelper = $configHelper;
        $this->objectManagerInterface = $objectManagerInterface;
        $this->storeManager = empty($storeManager) ?
            $objectManagerInterface->get('Magento\Store\Model\StoreManagerInterface') :
            $storeManager;
        $this->request = $request;
    }

    /**
     * @param Observer $observer
     * @param bool $checkOwnEventsMethods
     */
    public function execute(Observer $observer, $checkOwnEventsMethods = true)
    {
        try {
            /** @var $order Order */
            $order = $observer->getEvent()->getOrder();

            if (!is_object($order)) {
                return;
            }

            if ($this->configHelper->isEnabled($order) == false) {
                return;
            }

            $saveOrder = false;

            // Saving store code to order, to know where the order is been created
            if (empty($order->getData('origin_store_code')) && is_object($this->storeManager)) {
                $storeCode = $this->storeManager->getStore($this->helper->isAdmin() ? 'admin' : true)->getCode();

                if (!empty($storeCode)) {
                    $order->setData('origin_store_code', $storeCode);
                    $saveOrder = true;
                }
            }

            // Fix for Magento bug https://github.com/magento/magento2/issues/7227
            // x_forwarded_for should be copied from quote, but quote does not have the field on database
            if (empty($order->getData('x_forwarded_for')) && is_object($this->request)) {
                $xForwardIp = $this->request->getServer('HTTP_X_FORWARDED_FOR');

                if (empty($xForwardIp) == false) {
                    $order->setData('x_forwarded_for', $xForwardIp);
                    $saveOrder = true;
                }
            }

            if ($saveOrder) {
                $order->save();
            }

            // Check if a payment is available for this order yet
            if ($order->getState() == \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
                return;
            }

            $paymentMethod = $order->getPayment()->getMethod();
            $this->logger->debug($paymentMethod);

            if ($checkOwnEventsMethods && in_array($paymentMethod, $this->ownEventsMethods)) {
                return;
            }

            if ($this->isRestricted($paymentMethod, $order->getState())) {
                $this->logger->debug('Case creation for order ' . $order->getIncrementId() . ' with state ' . $order->getState() . ' is restricted');
                return;
            }

            // Check if case already exists for this order
            if ($this->helper->doesCaseExist($order)) {
                // backup hold order
                $this->holdOrder($order);
                return;
            }

            $orderData = $this->helper->processOrderData($order);

            // Add order to database
            $case = $this->helper->createNewCase($order);

            // Post case to signifyd service
            $result = $this->helper->postCaseToSignifyd($orderData, $order);

            // Initial hold order
            $this->holdOrder($order);

            if ($result){
                $case->setCode($result);
                $case->setMagentoStatus(CaseRetry::IN_REVIEW_STATUS)->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                try {
                    $case->getResource()->save($case);
                    $this->logger->debug('Case saved. Order No:' . $order->getIncrementId());
                } catch (\Exception $e) {
                    $this->logger->error('Exception in: ' . __FILE__ . ', on line: ' . __LINE__);
                    $this->logger->error('Exception:' . $e->__toString());
                }
            }
        } catch (\Exception $ex) {
            $this->logger->error($ex->getMessage());
        }
    }

    /**
     * Used on UpgradeScheme starting on version 3.2.0 for backward compatibility
     *
     * Do not remove this method
     *
     * Tries to get restricted payment methods from class property
     *
     * @return array
     * @deprecated
     */
    public function getOldRestrictMethods()
    {
        if (isset($this->restrictedMethods) && empty($this->restrictedMethods) == false) {
            return $this->restrictedMethods;
        } else {
            return false;
        }
    }

    /**
     * Get restricted payment methods from store configs
     *
     * @return array|mixed
     */
    public function getRestrictedPaymentMethodsConfig()
    {
        $restrictedPaymentMethods = $this->configHelper->getConfigData('signifyd/general/restrict_payment_methods');
        $restrictedPaymentMethods = explode(',', $restrictedPaymentMethods);
        $restrictedPaymentMethods = array_map('trim', $restrictedPaymentMethods);

        return $restrictedPaymentMethods;
    }

    /**
     * Check if there is any restrictions by payment method or state
     *
     * @param $method
     * @param null $state
     * @return bool
     */
    public function isRestricted($paymentMethodCode, $state, $action='default')
    {
        if (empty($state)) {
            return true;
        }

        $restrictedPaymentMethods = $this->getRestrictedPaymentMethodsConfig();

        if (in_array($paymentMethodCode, $restrictedPaymentMethods)) {
            return true;
        }

        return $this->isStateRestricted($state, $action);
    }

    /**
     * Check if state is restricted
     *
     * @param $state
     * @param string $action
     * @return bool
     */
    public function isStateRestricted($state, $action='default')
    {
        $restrictedStates = $this->configHelper->getConfigData("signifyd/general/restrict_states_{$action}");
        $restrictedStates = explode(',', $restrictedStates);
        $restrictedStates = array_map('trim', $restrictedStates);
        $restrictedStates = array_filter($restrictedStates);

        if (empty($restrictedStates) && $action != 'default') {
            return $this->isStateRestricted($state, 'default');
        }

        if (in_array($state, $restrictedStates)) {
            return true;
        }

        return false;
    }

    /**
     * @param $order
     * @return bool
     */
    public function holdOrder($order)
    {
        $case = $this->helper->getCase($order);
        $positiveAction = $case->getPositiveAction();
        $negativeAction = $case->getNegativeAction();

        if (($positiveAction != 'nothing' || $negativeAction != 'nothing')) {
            if (!$order->canHold()) {
                $notHoldableStates = [
                    Order::STATE_CANCELED,
                    Order::STATE_PAYMENT_REVIEW,
                    Order::STATE_COMPLETE,
                    Order::STATE_CLOSED,
                    Order::STATE_HOLDED
                ];

                if (in_array($order->getState(), $notHoldableStates)) {
                    $reason = "order is on {$order->getState()} state";
                } elseif ($order->getActionFlag(Order::ACTION_FLAG_HOLD) === false) {
                    $reason = "order action flag is set to do not hold";
                } else {
                    $reason = "unknown reason";
                }

                $this->logger->debug("Order {$order->getIncrementId()} can not be held because {$reason}");

                return false;
            }

            if (in_array($order->getPayment()->getMethod(), $this->specialMethods)) {
                if (!$order->getEmailSent()) {
                    return false;
                }

                if ($this->helper->hasGuaranty($order)) {
                    return false;
                }
            }

            if (!$this->helper->hasGuaranty($order)) {
                $this->logger->debug('Purchase Observer Order Hold: No: ' . $order->getIncrementId());
                $order->hold()->getResource()->save($order);
            }
        }

        return true;
    }
}
