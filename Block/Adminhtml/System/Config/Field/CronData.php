<?php

namespace Signifyd\Connect\Block\Adminhtml\System\Config\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Signifyd\Connect\Model\SignifydFlags;

class CronData extends Field
{
    protected $_template = 'Signifyd_Connect::system/config/field/cron_data.phtml';

    /**
     * @var SignifydFlags
     */
    public $signifydFlags;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param SignifydFlags $signifydFlags
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        SignifydFlags $signifydFlags,
        array $data = []
    ) {
        $this->signifydFlags = $signifydFlags;
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Get the last cron data from the flags file
     *
     * @return string|null
     */
    public function getLastCronData()
    {
        $flags = $this->signifydFlags->readFlags();
        if (isset($flags['cron'])) {
            return $flags['cron'];
        }
        return null;
    }
}
