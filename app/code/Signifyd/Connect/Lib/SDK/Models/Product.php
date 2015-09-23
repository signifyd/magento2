<?php
// namespace Models;
namespace Signifyd\Connect\Lib\SDK\Models;

use Signifyd\Connect\Lib\SDK\Core\SignifydModel;

/**
 * Class Product
 * Info on a particular item in the order.
 * @package Signifyd\Connect\Lib\SDK\Models
 */
class Product extends SignifydModel
{
    public $itemId;
    public $itemName;
    public $itemUrl;
    public $itemQuality;
    public $itemPrice;
    public $itemWeight;

    public function __construct()
    {
        $validator = array();
        $validator["itemId"] = array("type" => "string", "value" => null);
        $validator["itemName"] = array ("type" => "string", "value" => null);
        $validator["itemUrl"] = array("type" => "string", "value" => null);
        $validator["itemQuality"] = array ("type" => "string", "value" => null);
        $validator["itemPrice"] = array("type" => "string", "value" => null);
        $validator["itemWeight"] = array("type" => "string", "value" => null);

        $this->validationInfo = $validator;
    }
}
