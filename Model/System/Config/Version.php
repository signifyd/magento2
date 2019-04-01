<?php

namespace Signifyd\Connect\Model\System\Config;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\Module\ResourceInterface as ResourceInterface;

/**
 * Defines link data for the comment field in the config page
 */
class Version implements CommentInterface
{
    /**
     * @var ResourceInterface
     */
    protected $moduleResource;

    public function __construct(ResourceInterface $moduleResource)
    {
        $this->moduleResource = $moduleResource;
    }

    public function getCommentText($elementValue)
    {
        return $this->moduleResource->getDbVersion('Signifyd_Connect');
    }
}