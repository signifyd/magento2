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
            $url = $this->_urlInterface->getRouteUrl("signifyd/webhooks/index");
            $url = str_replace("/admin", "", $url);
            $url = "<a href=\"" . $url . "\">$url</a>";
        } else {
            $url = "{{store url}}/signifyd/webhooks/index";
        }
        return "Scores will be updated via webhooks. Please setup webhooks on your Signifyd account page (Webhook URL for this site is $url";
    }
}
