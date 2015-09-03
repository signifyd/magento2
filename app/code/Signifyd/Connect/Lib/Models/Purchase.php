<?php
// namespace Models;
namespace Signifyd\Connect\Lib\SDK\Models;

use Signifyd\Connect\Lib\SDK\Core\SignifydModel;

/**
 * Class Purchase
 * Info on the placed order
 * @package Signifyd\Connect\Lib\SDK\Models
 */
class Purchase extends SignifydModel
{
    public $browserIpAddress;
    public $orderId;
    public $createdAt; // datetime
    public $paymentGateway;
    public $currency;
    public $avsResponseCode;
    public $cvvResponseCode;
    public $orderChannel;
    public $receivedBy;
    public $totalPrice; //double
    public $products; // array
    public $shipments; // array

    public function __construct()
    {
        $validator = array();
        $validator["browserIpAddress"] = array("type" => "string", "value" => null);
        $validator["orderId"] = array ("type" => "string", "value" => null);
        $validator["createdAt"] = array("type" => "string", "value" => null);
        $validator["paymentGateway"] = array ("type" => "string", "value" => null);
        $validator["currency"] = array("type" => "string", "value" => null);
        $validator["avsResponseCode"] = array("type" => "string", "value" => null);
        $validator["cvvResponseCode"] = array ("type" => "string", "value" => null);
        $validator["orderChannel"] = array("type" => "string", "value" => null);
        $validator["receivedBy"] = array ("type" => "string", "value" => null);
        $validator["totalPrice"] = array("type" => "double", "value" => null);
        $validator["products"] = array ("type" => "array", "value" => null);
        $validator["shipments"] = array("type" => "array", "value" => null);

        $this->validationInfo = $validator;
    }
}
