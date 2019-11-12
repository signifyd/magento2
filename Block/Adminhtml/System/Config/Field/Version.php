<?php

namespace Signifyd\Connect\Block\Adminhtml\System\Config\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Module\ResourceInterface;

/**
 * Defines link data for the comment field in the config page
 */
class Version extends Field
{
    /**
     * @var ResourceInterface
     */
    protected $moduleResource;

    public function __construct(
        ResourceInterface $moduleResource,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        $this->moduleResource = $moduleResource;

        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->moduleResource->getDbVersion('Signifyd_Connect');
    }
}
