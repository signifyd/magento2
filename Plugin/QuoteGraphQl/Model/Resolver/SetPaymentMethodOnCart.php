<?php

namespace Signifyd\Connect\Plugin\QuoteGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Resolver\SetPaymentMethodOnCart as MagentoSetPaymentMethodOnCart;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;

class SetPaymentMethodOnCart
{
    /**
     * @var CartRepositoryInterface
     */
    public $cartRepository;

    /**
     * @var MaskedQuoteIdToQuoteId
     */
    public $maskedQuoteIdToQuoteId;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId,
        Logger $logger,
        ConfigHelper $configHelper
    ) {
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->logger = $logger;
        $this->configHelper = $configHelper;
    }

    /**
     * After resolve method.
     *
     * @param MagentoSetPaymentMethodOnCart $subject
     * @param mixed $result
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed
     */
    public function afterResolve(
        MagentoSetPaymentMethodOnCart $subject,
        mixed $result,
        Field $field,
        mixed $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): mixed {
        try {
            $input = $args['input']['payment_method'] ?? null;
            if (!$input) {
                return $result;
            }

            $preauth = $input['signifyd_preauth_data'] ?? null;
            if (!$preauth) {
                return $result;
            }

            $paymentMethod = $input['code'] ?? null;
            if (!$paymentMethod) {
                return $result;
            }

            $maskedId = $args['input']['cart_id'];
            $realQuoteId = $this->maskedQuoteIdToQuoteId->execute($maskedId);

            $cart = $this->cartRepository->get($realQuoteId);
            $payment = $cart->getPayment();

            $policyName = $this->configHelper->getPolicyName(
                $cart->getStore()->getScopeType(),
                $cart->getStoreId()
            );

            $isPreAuth = $this->configHelper->getIsPreAuth(
                $policyName,
                $paymentMethod,
                $cart->getStore()->getScopeType(),
                $cart->getStoreId()
            );

            if ($isPreAuth === false) {
                return $result;
            }

            if (isset($preauth['cardBin'])) {
                $payment->setAdditionalInformation('cardBin', $preauth['cardBin']);
            }

            if (isset($preauth['holderName'])) {
                $payment->setAdditionalInformation('holderName', $preauth['holderName']);
            }

            if (isset($preauth['cardLast4'])) {
                $payment->setAdditionalInformation('cardLast4', $preauth['cardLast4']);
            }

            if (isset($preauth['cardExpiryMonth'])) {
                $payment->setAdditionalInformation('cardExpiryMonth', $preauth['cardExpiryMonth']);
            }

            if (isset($preauth['cardExpiryYear'])) {
                $payment->setAdditionalInformation('cardExpiryYear', $preauth['cardExpiryYear']);
            }

            $this->cartRepository->save($cart);
        } catch (\Exception $e) {
            $this->logger->info("Graphql: Failed to save pre auth data: " . $e->getMessage());
        } catch (\Error $e) {
            $this->logger->info("Graphql: Failed to save pre auth data: " . $e->getMessage());
        }

        return $result;
    }
}
