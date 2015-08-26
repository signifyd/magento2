<?php

// namespace core;
namespace Signifyd\Connect\Lib\SDK\core;

/**
 * Class SignifydSettings
 * Stores all of the options required for the API itself. Specific integrations may have their own settings
 */
class SignifydSettings
{
    /**
     * @var string API key used for authorization with Signifyd service
     * You can find the key value at http://signifyd.com/settings/teams
     */
    public $apiKey;

    /**
     * @var bool Whether or not to validate inputs before executing API calls. For diagnostic purposes
     */
    public $validateData = false;

    /**
     * @var bool Whether to log errors. Recommended
     */
    public $logErrors = true;

    /**
     * @var bool Whether to log warnings. Recommended
     */
    public $logWarnings = true;

    /**
     * @var bool Whether to log trace statements. Only for diagnostic purposes.
     */
    public $logInfo = false;

    /**
     * @var callable Function which will be used for logging errors.
     * Takes one argument, the message body
     */
    public $loggerError;

    /**
     * @var callable Function which will be used for logging warnings.
     * Takes one argument, the message body
     */
    public $loggerWarning;

    /**
     * @var callable Function which will be used for logging info.
     * Takes one argument, the message body
     */
    public $loggerInfo;

    /**
     * @var int CURL timeout value.
     */
    public $timeout = 12;
}
