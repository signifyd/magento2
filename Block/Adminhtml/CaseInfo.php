<?php

namespace Signifyd\Connect\Block\Adminhtml;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Shipping\Helper\Data as ShippingHelper;
use Magento\Tax\Helper\Data as TaxHelper;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\ResourceModel\Logs\CollectionFactory as LogsCollectionFactory;

/**
 * Get Signifyd Case Info
 *
 * @api
 * @since 100.2.0
 */
class CaseInfo extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder
{
    /**
     * @var Casedata
     */
    public $caseEntity;

    /**
     * @var LogsCollectionFactory
     */
    public $logsCollectionFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * CaseInfo constructor.
     * @param Casedata $caseEntity
     * @param LogsCollectionFactory $logsCollectionFactory
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Sales\Helper\Admin $adminHelper
     * @param ProductMetadataInterface $productMetadataInterface
     * @param CasedataResourceModel $casedataResourceModel
     * @param CasedataFactory $casedataFactory
     * @param array $data
     * @param ShippingHelper|null $shippingHelper
     * @param TaxHelper|null $taxHelper
     */
    public function __construct(
        Casedata $caseEntity,
        LogsCollectionFactory $logsCollectionFactory,
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Helper\Admin $adminHelper,
        ProductMetadataInterface $productMetadataInterface,
        CasedataResourceModel $casedataResourceModel,
        CasedataFactory $casedataFactory,
        array $data = [],
        ?ShippingHelper $shippingHelper = null,
        ?TaxHelper $taxHelper = null
    ) {
        //Backward compatibility with Magento 2.3, in this version the parent
        // construct don't have $shippingHelper and $taxHelper parameters, causing di:compile error
        $this->initConstructor(
            $productMetadataInterface,
            $context,
            $registry,
            $adminHelper,
            $data,
            $shippingHelper,
            $taxHelper
        );

        $this->caseEntity = $caseEntity;
        $this->logsCollectionFactory = $logsCollectionFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->casedataFactory = $casedataFactory;
    }

    public function initConstructor(
        $productMetadataInterface,
        $context,
        $registry,
        $adminHelper,
        $data,
        $shippingHelper,
        $taxHelper
    ) {
        if (version_compare($productMetadataInterface->getVersion(), '2.4.0') >= 0) {
            parent::__construct(
                $context,
                $registry,
                $adminHelper,
                $data,
                $shippingHelper,
                $taxHelper
            );
        } else {
            parent::__construct(
                $context,
                $registry,
                $adminHelper,
                $data
            );
        }
    }

    /**
     * Gets case entity associated with order id.
     *
     * @return Casedata|null
     */
    public function getCaseEntity()
    {
        if ($this->caseEntity->isEmpty()) {
            $order = $this->getOrder();
            if (!$order->isEmpty()) {
                $case = $this->casedataFactory->create();
                $this->casedataResourceModel->load($case, $order->getId(), 'order_id');
                $this->caseEntity = $case;
            }
        }

        return $this->caseEntity;
    }

    /**
     * Gets case guarantee disposition status
     */
    public function getCaseGuaranteeDisposition()
    {
        if ($this->getCaseEntity()->getData('guarantee') == "APPROVED") {
            $labelGuarantee = 'ACCEPT';
        } elseif ($this->getCaseEntity()->getData('guarantee') == "DECLINED") {
            $labelGuarantee = 'REJECT';
        } elseif ($this->getCaseEntity()->getData('guarantee') == "PENDING") {
            $labelGuarantee = 'HOLD';
        } else {
            $labelGuarantee = $this->getCaseEntity()->getData('guarantee');
        }

        return $labelGuarantee;
    }

    /**
     * Gets case score
     */
    public function getCaseScore()
    {
        $score = $this->getCaseEntity()->getData('score');

        if (isset($score) === false) {
            return 0;
        }

        return floor($this->getCaseEntity()->getData('score'));
    }

    /**
     * @return array|mixed|null
     */
    public function getCheckpointActionReason()
    {
        return $this->getCaseEntity()->getData('checkpoint_action_reason');
    }

    /**
     * @return array|mixed|null
     */
    public function hasLogsToDownload()
    {
        $order = $this->getOrder();

        $quoteLogsCollection = $this->logsCollectionFactory->create()
            ->addFieldToFilter('quote_id', ['eq' => $order->getQuoteId()]);

        $orderLogsCollection = $this->logsCollectionFactory->create()
            ->addFieldToFilter('order_id', ['eq' => $order->getId()]);

        return $orderLogsCollection->count() > 0 || $quoteLogsCollection->count() > 0;
    }
}
