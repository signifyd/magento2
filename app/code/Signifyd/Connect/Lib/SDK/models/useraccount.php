<?php
// namespace models;
namespace Signifyd\Connect\Lib\SDK\models;

use Signifyd\Connect\Lib\SDK\core\SignifydModel;

/**
 * Class UserAccount
 * Info for the account that placed the order. May not be the recipient
 * @package Signifyd\Connect\Lib\SDK\models
 */
class UserAccount extends SignifydModel
{
    public $emailAddress;
    public $username;
    public $phone;
    public $createdDate;
    public $accountNumber;
    public $lastOrderId;
    public $aggregateOrderCount;
    public $aggregateOrderDollars;
    public $lastUpdateDate;

    public function __construct()
    {
        $validator = array();
        $validator["emailAddress"] = array("type" => "string", "value" => null);
        $validator["username"] = array ("type" => "string", "value" => null);
        $validator["phone"] = array("type" => "string", "value" => null);
        $validator["accountNumber"] = array("type" => "string", "value" => null);
        $validator["createdDate"] = array ("type" => "string", "value" => null);
        $validator["lastOrderId"] = array("type" => "string", "value" => null);
        $validator["aggregateOrderCount"] = array("type" => "string", "value" => null);
        $validator["aggregateOrderDollars"] = array ("type" => "string", "value" => null);
        $validator["lastUpdateDate"] = array("type" => "string", "value" => null);

        $this->validationInfo = $validator;
    }
}
