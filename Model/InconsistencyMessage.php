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
    public const MESSAGE_IDENTITY = 'signifyd_system_message';

    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * @var UrlInterface
     */
    public $urlInterface;

    /**
     * InconsistencyMessage construct.
     *
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

    /**
     * Get identity method.
     *
     * @return string
     */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * Is displayed method.
     *
     * @return bool
     */
    public function isDisplayed()
    {
        $upgradeInconsistency = $this->scopeConfigInterface->getValue("signifyd/general/upgrade4.3_inconsistency");

        if (isset($upgradeInconsistency) && $upgradeInconsistency !== "fixed") {
            return true;
        }

        return false;
    }

    /**
     * Get text method.
     *
     * @return \Magento\Framework\Phrase|string
     */
    public function getText()
    {
        return __("Signifyd database inconsistency detected. This required immediate fix. Learn how on " .
        " <a href='https://github.com/signifyd/magento2/blob/master/docs/DATABASE-INCONSISTENCY.md' target='_blank'>
            this link</a>. " .
        "After fix the issue, you can <a href='" . $this->getFixUrl() . "'>mark as fixed</a>.");
    }

    /**
     * Get severity method.
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }

    /**
     * Get fix url method.
     *
     * @return string
     */
    public function getFixUrl()
    {
        return $this->urlInterface->getUrl('signifyd_backend/markasfixed/index');
    }
}
