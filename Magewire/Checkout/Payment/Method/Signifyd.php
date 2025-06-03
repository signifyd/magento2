<?php
declare(strict_types=1);

namespace Signifyd\Connect\Magewire\Checkout\Payment\Method;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magewirephp\Magewire\Component\Form;
use Rakit\Validation\Validator;

class Signifyd extends Form
{
    /**
     * @var Session
     */
    private Session $sessionCheckout;

    /**
     * @var QuoteResourceModel
     */
    private QuoteResourceModel $quoteResourceModel;

    /**
     * Signifyd constructor.
     *
     * @param Validator $validator
     * @param Session $sessionCheckout
     * @param QuoteResourceModel $quoteResourceModel
     */
    public function __construct(
        Validator $validator,
        Session $sessionCheckout,
        QuoteResourceModel $quoteResourceModel
    ) {
        $this->sessionCheckout = $sessionCheckout;
        $this->quoteResourceModel = $quoteResourceModel;
        parent::__construct($validator);
    }

    /**
     * Set setCardData
     *
     * @param array $cardDetails
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function setCardData(array $cardDetails): void
    {
        $quote = $this->sessionCheckout->getQuote();

        $quote->getPayment()
            ->setAdditionalInformation(
                $cardDetails
            );

        $this->quoteResourceModel->save($quote);
    }
}
