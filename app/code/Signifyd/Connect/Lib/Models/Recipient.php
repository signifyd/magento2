<?php
// namespace Models;
namespace Signifyd\Connect\Lib\SDK\Models;

use Signifyd\Connect\Lib\SDK\Core\SignifydModel;

/**
 * Class Recipient
 * Info on the person who will receive the order. May not be that same as the person who placed it.
 * @package Signifyd\Connect\Lib\SDK\Models
 */
class Recipient extends SignifydModel
{
    public $fullName;
    public $confirmationEmail;
    public $confirmationPhone;
    public $organization;
    public $deliveryAddress;

    public function __construct()
    {
        $validator = array();
        $validator["fullName"] = array("type" => "string", "value" => null);
        $validator["confirmationEmail"] = array ("type" => "string", "value" => null);
        $validator["confirmationPhone"] = array("type" => "string", "value" => null);
        $validator["organization"] = array ("type" => "string", "value" => null);
        $validator["deliveryAddress"] = array("type" => "SignifydModel", "value" => array(
            "subtype" => "Address"
        ));

        $this->validationInfo = $validator;
    }
}
