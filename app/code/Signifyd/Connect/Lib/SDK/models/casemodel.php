<?php
// namespace models;
namespace Signifyd\Connect\Lib\SDK\models;

use Signifyd\Connect\Lib\SDK\core\SignifydModel;

/**
 * Class CaseModel
 * Top level object model for a new case entry
 * @package Signifyd\Connect\Lib\SDK\models
 */
class CaseModel extends SignifydModel
{
    /**
     * @var \Signifyd\Connect\Lib\SDK\models\Purchase
     */
    public $purchase;
    /**
     * @var \Signifyd\Connect\Lib\SDK\models\Recipient
     */
    public $recipient;
    /**
     * @var \Signifyd\Connect\Lib\SDK\models\Card
     */
    public $card;
    /**
     * @var \Signifyd\Connect\Lib\SDK\models\UserAccount
     */
    public $userAccount;
    /**
     * @var \Signifyd\Connect\Lib\SDK\models\Seller
     */
    public $seller;

    public function __construct()
    {
        $validator = array();
        $validator["purchase"] = array("type" => "SignifydModel", "value" => array(
            "subtype" => "Purchase"
        ));
        $validator["recipient"] = array ("type" => "SignifydModel", "value" => array(
            "subtype" => "Recipient"
        ));
        $validator["card"] = array("type" => "SignifydModel", "value" => array(
            "subtype" => "Card"
        ));
        $validator["userAccount"] = array ("type" => "SignifydModel", "value" => array(
            "subtype" => "UserAccount"
        ));
        $validator["seller"] = array("type" => "SignifydModel", "value" => array(
            "subtype" => "Seller"
        ));

        $this->validationInfo = $validator;
    }
}
