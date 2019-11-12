<?php

namespace Signifyd\Connect\Api\Core;

/**
 * Jira issue MAG-302: fix for Magento bug on versions bellow 2.2.0
 * Magento issue: https://github.com/magento/magento2/issues/9760
 * This class can be removed when all Signifyd customers are using a version 2.2.0 or higher
 * After remove this class it is necessary to replace all its occurrences with \Signifyd\Core\SignifydAPIFactory
 *
 * Class SignifydAPIFactory
 * @package Signifyd\Connect\Api\Core
 */
class SignifydAPIFactory extends \Signifyd\Core\SignifydAPIFactory
{
}
