<?php


/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging Level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * The Signifyd file log name
     * @var string
     */
    protected $fileName = '/var/log/signifyd_connect.log';
}