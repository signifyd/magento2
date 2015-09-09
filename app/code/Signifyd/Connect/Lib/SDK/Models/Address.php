<?php
// namespace Models;
namespace Signifyd\Connect\Lib\SDK\Models;

use Signifyd\Connect\Lib\SDK\Core\SignifydModel;


/**
 * Class Address
 * Contains a shipping/billing address
 * @package Signifyd\Connect\Lib\SDK\Models
 */
class Address extends SignifydModel
{
    /**
     * @var string Main street address
     */
    public $streetAddress;
    /**
     * @var string Address line 2
     */
    public $unit;
    /**
     * @var string City name
     */
    public $city;
    /**
     * @var string Province (optional)
     */
    public $provinceCode;
    /**
     * @var string Local postal code.
     */
    public $postalCode;
    /**
     * @var string Country code
     */
    public $countryCode;
    /**
     * @var string Actual latitude
     */
    public $latitude;
    /**
     * @var string actual longitude
     */
    public $longitude;

    public function __construct()
    {
        $validator = array();
        $validator["streetAddress"] = array("type" => "string", "value" => null);
        $validator["unit"] = array ("type" => "string", "value" => null);
        $validator["city"] = array("type" => "string", "value" => null);
        $validator["provinceCode"] = array ("type" => "string", "value" => null);
        $validator["postalCode"] = array("type" => "string", "value" => null);
        $validator["countryCode"] = array ("type" => "string", "value" => null);
        $validator["latitude"] = array ("type" => "string", "value" => null);
        $validator["longitude"] = array ("type" => "string", "value" => null);

        $this->validationInfo = $validator;
    }
}
