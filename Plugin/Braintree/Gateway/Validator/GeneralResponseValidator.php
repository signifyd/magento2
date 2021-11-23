<?php

namespace Signifyd\Connect\Plugin\Braintree\Gateway\Validator;

use Magento\Braintree\Gateway\SubjectReader;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\Casedata;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;

class GeneralResponseValidator extends \Signifyd\Connect\Plugin\Braintree\GeneralResponseValidator
{
    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var QuoteResourceModel
     */
    protected $quoteResourceModel;

    /**
     * @var SubjectReader
     */
    protected $subjectReader;

    /**
     * CheckoutPaymentsDetailsHandler constructor.
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param Logger $logger
     * @param PurchaseHelper $purchaseHelper
     * @param StoreManagerInterface $storeManager
     * @param QuoteFactory $quoteFactory
     * @param QuoteResourceModel $quoteResourceModel
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger,
        PurchaseHelper $purchaseHelper,
        StoreManagerInterface $storeManager,
        QuoteFactory $quoteFactory,
        QuoteResourceModel $quoteResourceModel,
        SubjectReader $subjectReader
    ) {
        parent::__construct(
            $casedataFactory,
            $casedataResourceModel,
            $logger,
            $purchaseHelper,
            $storeManager,
            $quoteFactory,
            $quoteResourceModel
        );
        $this->subjectReader = $subjectReader;
    }
}
