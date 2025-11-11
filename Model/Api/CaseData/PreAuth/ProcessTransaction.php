<?php

namespace Signifyd\Connect\Model\Api\CaseData\PreAuth;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Api\TransactionsFactory;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;

class ProcessTransaction
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var TransactionsFactory
     */
    public $transactionsFactory;

    /**
     * @var Client
     */
    public $client;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * Transaction constructor.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param ConfigHelper $configHelper
     * @param TransactionsFactory $transactionsFactory
     * @param Client $client
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        Logger $logger,
        CasedataFactory $casedataFactory,
        ConfigHelper $configHelper,
        TransactionsFactory $transactionsFactory,
        Client $client,
        JsonSerializer $jsonSerializer
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->configHelper = $configHelper;
        $this->transactionsFactory = $transactionsFactory;
        $this->client = $client;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Process pre auth transactions
     *
     * @param \Magento\Sales\Model\Order $order
     *
     * @return void
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Signifyd\Core\Exceptions\ApiException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     */
    public function __invoke($order)
    {
        $paymentMethod = $order->getPayment()->getMethod();

        if ($this->configHelper->isPaymentRestricted($paymentMethod)) {
            $message = 'Case creation for order ' . $order->getIncrementId() . ' with payment ' .
                $paymentMethod . ' is restricted';
            $this->logger->debug($message, ['entity' => $order]);
            return;
        }

        $customerGroupId = $order->getCustomerGroupId();

        if ($this->configHelper->isCustomerGroupRestricted($customerGroupId)) {
            $message = 'Case creation for order ' . $order->getIncrementId() . ' with customer group id ' .
                $customerGroupId . ' is restricted';
            $this->logger->debug($message, ['entity' => $order]);
            return;
        }

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataRepository->getByOrderId($order->getId());

        if ($case->isEmpty() == false && $case->getPolicyName() == Casedata::PRE_AUTH) {
            $makeTransactions = $this->transactionsFactory->create();
            $saleTransaction = [];
            $saleTransaction['checkoutId'] = $case->getCheckoutToken();
            $saleTransaction['orderId'] = $order->getIncrementId();
            $saleTransaction['transactions'] = $makeTransactions($order);

            $saleTransactionJson = $this->jsonSerializer->serialize($saleTransaction);
            $newHashToValidateReroute = sha1($saleTransactionJson);
            $currentHashToValidateReroute = $case->getEntries('transaction_hash');

            if ($newHashToValidateReroute == $currentHashToValidateReroute) {
                $this->logger->info(
                    'No data changes, will not send transaction ' .
                    $order->getIncrementId(),
                    ['entity' => $order]
                );
                return;
            }

            $this->logger->info("Sending pre_auth transaction to Signifyd for order
                        {$case->getOrderIncrement()}", ['entity' => $order]);

            $this->client->postTransactionToSignifyd($saleTransaction, $order);
            $case->setEntries('transaction_hash', $newHashToValidateReroute);
            $this->casedataRepository->save($case);
        }
    }
}
