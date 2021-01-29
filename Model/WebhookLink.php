<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model;

use Magento\Config\Model\Config\CommentInterface;

/**
 * Defines link data for the comment field in the config page
 */
class WebhookLink implements CommentInterface
{
    /**
     * @var \Magento\Framework\Url
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * WebhookLink constructor.
     * @param \Magento\Framework\Url $urlInterface
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\Url $urlInterface,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->urlBuilder = $urlInterface;
        $this->storeManager = $storeManager;
        $this->request = $request;
    }

    /**
     * @param string $elementValue
     * @return string
     */
    public function getCommentText($elementValue)
    {
        $url = $this->getUrl();

        return "<a href='{$url}'>{$url}</a> <br /> Use this URL to setup your Magento " .
            "<a href='https://app.signifyd.com/settings/notifications' target='_blank'>webhook</a> " .
            "from the Signifyd console. You MUST setup the webhook to enable order workflows ".
            "and syncing of guarantees back to Magento.";
    }

    public function getUrl()
    {
        if ($this->urlBuilder != null) {
            $storeId = $this->request->getParam('store');
            $websiteId = $this->request->getParam('website');

            if (empty($websiteId)) {
                $storeId = empty($storeId) ? $this->storeManager->getDefaultStoreView()->getId() : $storeId;
            } else {
                $storeId = $this->storeManager->getWebsite($websiteId)->getDefaultStore()->getId();
            }

            $this->urlBuilder->setScope($storeId);
            $url = $this->urlBuilder->getUrl('signifyd_connect/webhooks/index', ['_nosid' => true]);
        } else {
            $url = "{{store url}}/signifyd_connect/webhooks/index";
        }

        return $url;
    }
}
