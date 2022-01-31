<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;

/**
 * Class CustomNotification
 */
class InconsistencyMessage implements MessageInterface
{
    const MESSAGE_IDENTITY = 'signifyd_system_message';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param UrlInterface $urlInterface
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        UrlInterface $urlInterface
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->urlInterface = $urlInterface;
    }

    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    public function isDisplayed()
    {
        $upgradeInconsistency = $this->scopeConfigInterface->getValue("signifyd/general/upgrade4.3_inconsistency");

        if (isset($upgradeInconsistency) && $upgradeInconsistency !== "fixed") {
            return true;
        }

        return false;
    }

    public function getText()
    {
        return __("Signifyd database inconsistency detected. This required immediate fix. Learn how on " .
        " <a href='https://github.com/signifyd/magento2/blob/master/docs/DATABASE-INCONSISTENCY.md' target='_blank'>
            this link</a>. " .
        "After fix the issue, you can <a href='" . $this->getFixUrl() . "'>mark as fixed</a>.");
    }

    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }

    public function getFixUrl()
    {
        return $this->urlInterface->getUrl('signifyd_backend/markasfixed/index');
    }
}
