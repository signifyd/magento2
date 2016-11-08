<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\UrlInterface;


/**
 * Defines link data for the comment field in the config page
 */
class WebhookLink implements CommentInterface
{
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlInterface;

    public function __construct(
        UrlInterface $urlInterface
    ) {
        $this->_urlInterface = $urlInterface;
    }

    public function getCommentText($elementValue)
    {
        $url = "";
        if ($this->_urlInterface != null) {
            $url = $this->_urlInterface->getBaseUrl();
            $url = $url . 'signifyd/webhooks/index';
            $url = "<a href=\"" . $url . "\">$url</a>";
        } else {
            $url = "{{store url}}/signifyd/webhooks/index";
        }
        return "$url <br />Use this URL to setup your Magento <a href=\"https://app.signifyd.com/settings/notifications\">webhook</a> from the Signifyd console. You MUST setup the webhook to enable order workflows and syncing of guarantees back to Magento.";
    }
}
