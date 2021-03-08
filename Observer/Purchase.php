<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\State as AppState;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Purchase implements ObserverInterface
{
    /**
     * @var Logger;
     */
    protected $logger;

    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

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
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var AppState
     */
    protected $appState;

    /**
     * Purchase constructor.
     * @param Logger $logger
     * @param PurchaseHelper $purchaseHelper
     * @param ConfigHelper $configHelper
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param OrderResourceModel $orderResourceModel
     * @param DateTime $dateTime
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param StoreManagerInterface $storeManager
     * @param AppState $appState
     */
    public function __construct(
        Logger $logger,
        PurchaseHelper $purchaseHelper,
        ConfigHelper $configHelper,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        OrderResourceModel $orderResourceModel,
        DateTime $dateTime,
        ScopeConfigInterface $scopeConfigInterface,
        StoreManagerInterface $storeManager,
        AppState $appState
    ) {
        $this->logger = $logger;
        $this->purchaseHelper = $purchaseHelper;
        $this->configHelper = $configHelper;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->orderResourceModel = $orderResourceModel;
        $this->dateTime = $dateTime;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->storeManager = $storeManager;
        $this->appState = $appState;
    }

    /**
     * @param Observer $observer
     * @param bool $checkOwnEventsMethods
     */
    public function execute(Observer $observer, $checkOwnEventsMethods = true)
    {
        try {
            $this->logger->info('Processing Signifyd event ' . $observer->getEvent()->getName());

            /** @var $order Order */
            $order = $observer->getEvent()->getOrder();

            if (!is_object($order)) {
                return;
            }

            if ($this->configHelper->isEnabled($order) == false) {
                return;
            }

            $incrementId = $order->getIncrementId();

            if ($this->isIgnored($order)) {
                $this->logger->debug("Order {$incrementId} ignored");
                return;
            }

            /** @var $case \Signifyd\Connect\Model\Casedata */
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $order->getId(), 'order_id');

            if ($case->isEmpty()) {
                $case->setData('magento_status', Casedata::NEW);
                $case->setData('order_increment', $order->getIncrementId());
                $case->setData('order_id', $order->getId());

                if (is_object($this->storeManager)) {
                    $isAdmin = ('adminhtml' === $this->appState->getAreaCode());
                    $storeCode = $this->storeManager->getStore($isAdmin ? 'admin' : true)->getCode();
                    if (!empty($storeCode)) {
                        $case->setData('origin_store_code', $storeCode);
                    }
                }

                $this->casedataResourceModel->save($case);
            } elseif ($case->getData('magento_status') != Casedata::NEW) {
                return;
            }

            // Check if a payment is available for this order yet
            if ($order->getState() == \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
                return;
            }

            $paymentMethod = $order->getPayment()->getMethod();
            $state = $order->getState();

            $checkOwnEventsMethodsEvent = $observer->getEvent()->getCheckOwnEventsMethods();

            if ($checkOwnEventsMethodsEvent !== null) {
                $checkOwnEventsMethods = $checkOwnEventsMethodsEvent;
            }

            if ($checkOwnEventsMethods && in_array($paymentMethod, $this->ownEventsMethods)) {
                return;
            }

            if ($this->isRestricted($paymentMethod, $state, 'create')) {
                $message = 'Case creation for order ' . $incrementId . ' with state ' . $state . ' is restricted';
                $this->logger->debug($message, ['entity' => $order]);
                return;
            }

            $message = "Creating case for order {$incrementId} ({$order->getId()}),
            state {$state}, payment method {$paymentMethod}";
            $this->logger->debug($message, ['entity' => $order]);

            $case->setSignifydStatus("PENDING");
            $case->setCreated(strftime('%Y-%m-%d %H:%M:%S', time()));
            $case->setUpdated();
            $case->setEntriesText("");

            // Stop case sending if order has an async payment method
            if (in_array($paymentMethod, $this->getAsyncPaymentMethodsConfig())) {
                $case->setMagentoStatus(Casedata::ASYNC_WAIT);

                try {
                    $this->casedataResourceModel->save($case);
                    $this->logger->debug(
                        'Case for order:#' . $incrementId . ' was not sent because of an async payment method',
                        ['entity' => $case]
                    );

                    // Initial hold order
                    $this->holdOrder($order, $case);
                    $this->orderResourceModel->save($order);
                } catch (\Exception $ex) {
                    $this->logger->error($ex->__toString());
                }

                return;
            }

            $order->setData('origin_store_code', $case->getData('origin_store_code'));

            $orderData = $this->purchaseHelper->processOrderData($order);
            $caseResponse = $this->purchaseHelper->postCaseToSignifyd($orderData, $order);

            // Initial hold order
            $this->holdOrder($order, $case);

            if (is_object($caseResponse)) {
                $case->setCode($caseResponse->getCaseId());
                $case->setMagentoStatus(Casedata::IN_REVIEW_STATUS);
                $case->setUpdated();
            }

            $this->casedataResourceModel->save($case);
            $this->orderResourceModel->save($order);
        } catch (\Exception $ex) {
            $context = [];

            if (isset($order) && $order instanceof Order) {
                $context['entity'] = $order;
            }

            $this->logger->error($ex->getMessage(), $context);
        }
    }

    /**
     * Get async payment methods from store configs
     *
     * @return array|mixed
     */
    public function getAsyncPaymentMethodsConfig()
    {
        $asyncPaymentMethods = $this->configHelper->getConfigData('signifyd/general/async_payment_methods');
        $asyncPaymentMethods = explode(',', $asyncPaymentMethods);
        $asyncPaymentMethods = array_map('trim', $asyncPaymentMethods);

        return $asyncPaymentMethods;
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
    public function isRestricted($paymentMethodCode, $state, $action = 'default')
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
     * Check if order is ignored based on installation date
     *
     * If there is no record of the installation date on database order will not be ignored
     *
     * @param Order $order
     * @return bool
     */
    public function isIgnored(Order $order)
    {
        $installationDateConfig = $this->scopeConfigInterface->getValue('signifyd_connect/general/installation_date');

        if (empty($installationDateConfig)) {
            return false;
        }

        $installationDate = $this->dateTime->gmtTimestamp($installationDateConfig);
        $createdAtDate = $this->dateTime->gmtTimestamp($order->getCreatedAt());

        if ($createdAtDate < $installationDate) {
            $this->logger->info("Installation date: {$installationDate}");
            $this->logger->info("Created at date: {$createdAtDate}");

            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if state is restricted
     *
     * @param $state
     * @param string $action
     * @return bool
     */
    public function isStateRestricted($state, $action = 'default')
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
    public function holdOrder(\Magento\Sales\Model\Order $order, \Signifyd\Connect\Model\Casedata $case)
    {
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

                $message = "Order {$order->getIncrementId()} can not be held because {$reason}";
                $this->logger->debug($message, ['entity' => $order]);

                return false;
            }

            if (in_array($order->getPayment()->getMethod(), $this->specialMethods)) {
                if (!$order->getEmailSent()) {
                    return false;
                }
            }

            $this->logger->debug(
                'Purchase Observer Order Hold: No: ' . $order->getIncrementId(),
                ['entity' => $order]
            );

            $order->hold();
            $order->addCommentToStatusHistory("Signifyd: after order place");
        }

        return true;
    }
}
