<?php

namespace Signifyd\Connect\Controller;

class Router implements \Magento\Framework\App\RouterInterface
{
    /**
     * @var \Magento\Framework\App\ActionFactory
     */
    public $actionFactory;

    /**
     * @var \Magento\Framework\App\ResponseInterface
     */
    public $_response;

    /**
     * @param \Magento\Framework\App\ActionFactory $actionFactory
     * @param \Magento\Framework\App\ResponseInterface $response
     */
    public function __construct(
        \Magento\Framework\App\ActionFactory $actionFactory,
        \Magento\Framework\App\ResponseInterface $response
    ) {
        $this->actionFactory = $actionFactory;
        $this->_response = $response;
    }

    /**
     * Validate and Match
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @return \Magento\Framework\App\ActionInterface|void
     */
    public function match(\Magento\Framework\App\RequestInterface $request)
    {
        if ($request->getModuleName() === 'signifyd_connect') {
            return;
        }

        $identifier = trim($request->getPathInfo(), '/');

        if (strpos($identifier, 'signifyd/webhooks') !== false &&
            strpos($identifier, 'signifyd/webhooks/handler') === false
        ) {
            $request->setModuleName('signifyd_connect')->setControllerName('webhooks')->setActionName('index');
        } else {
            return;
        }

        return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }
}
