<?php

namespace Signifyd\Connect\Block\Adminhtml\Shopcart\Abandoned;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Stdlib\Parameters;
use Magento\Framework\Url\DecoderInterface;
use Magento\Store\Model\ScopeInterface;
use Signifyd\Connect\Helper\ConfigHelper;

class Grid extends \Magento\Reports\Block\Adminhtml\Shopcart\Abandoned\Grid
{
    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * Grid constructor.
     *
     * @param ProductMetadataInterface $productMetadataInterface
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Backend\Helper\Data $backendHelper
     * @param \Magento\Reports\Model\ResourceModel\Quote\CollectionFactory $quotesFactory
     * @param JsonSerializer $jsonSerializer
     * @param ConfigHelper $configHelper
     * @param DecoderInterface|null $urlDecoder
     * @param Parameters|null $parameters
     * @param array $data
     */
    public function __construct(
        ProductMetadataInterface $productMetadataInterface,
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Magento\Reports\Model\ResourceModel\Quote\CollectionFactory $quotesFactory,
        JsonSerializer $jsonSerializer,
        ConfigHelper $configHelper,
        DecoderInterface $urlDecoder = null,
        Parameters $parameters = null,
        array $data = []
    ) {
        //Backward compatibility with Magento 2.4.6 or less, in this version the parent
        // construct don't have $urlDecoder and $parameters parameters, causing di:compile error
        $this->initConstructor(
            $productMetadataInterface,
            $context,
            $backendHelper,
            $quotesFactory,
            $urlDecoder,
            $parameters,
            $data
        );

        $this->jsonSerializer = $jsonSerializer;
        $this->configHelper = $configHelper;
    }

    /**
     * @param $productMetadataInterface
     * @param $context
     * @param $backendHelper
     * @param $quotesFactory
     * @param $urlDecoder
     * @param $parameters
     * @param $data
     * @return void
     */
    public function initConstructor(
        $productMetadataInterface,
        $context,
        $backendHelper,
        $quotesFactory,
        $urlDecoder,
        $parameters,
        $data
    ) {
        if (version_compare($productMetadataInterface->getVersion(), '2.4.7') >= 0) {
            parent::__construct($context, $backendHelper, $quotesFactory, $urlDecoder, $parameters, $data);
        } else {
            parent::__construct($context, $backendHelper, $quotesFactory, $data);
        }
    }

    /**
     * Prepare columns
     *
     * @return \Magento\Backend\Block\Widget\Grid\Extended
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _prepareColumns()
    {
        $return = parent::_prepareColumns();

        if ($this->getRequest()->getParam('store')) {
            $storeId = (int)$this->getRequest()->getParam('store');
            $policyName = $this->configHelper->getPolicyName(ScopeInterface::SCOPE_STORES, $storeId);
        } else {
            $policyName = $this->configHelper->getPolicyName();
        }

        $isPreAuthInUse = $this->configHelper->getIsPreAuthInUse($policyName);

        if ($isPreAuthInUse) {
            $this->addColumn(
                'signifyd_guarantee',
                [
                    'header' => __('Signifyd Decision'),
                    'index' => 'signifyd_guarantee',
                    'sortable' => false,
                    'header_css_class' => 'col-name',
                    'column_css_class' => 'col-name',
                    'renderer' => \Signifyd\Connect\Block\Adminhtml\Grid\Column\SignifydGuarantee::class
                ]
            );
        }

        return $return;
    }
}
