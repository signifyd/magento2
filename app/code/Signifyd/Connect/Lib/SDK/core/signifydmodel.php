<?php

// namespace core;
namespace Signifyd\Connect\Lib\SDK\Core;

/**
 * Class SignifydModel Base class for all API model data. Handles data validation
 */
abstract class SignifydModel
{
    /**
     * @var array Meta data for validation.
     */
    protected $validationInfo = array();

    /**
     * Base constructor
     */
    public function __construct()
    {
    }

    /**
     * Serialize public data to json.
     * @return string JSON object
     */
    public function toJson()
    {
        $this->_validateObject();
        return json_encode($this);
    }

    /**
     * The idea here is that each type includes a validation method that ensures
     * the set values fall within the type requirements and value requirements
     * set by the Signifyd REST API.
     */
    protected function _validateObject()
    {
        // TODO
    }

    /**
     * Check integer related validation steps, such as whether the value falls within a certain range
     * @param int $value The instance value of the particular property
     * @param array $validator The validator metadata (for int types) as defined by the class
     * @return bool Whether or not the value passes the validation
     */
    protected function _checkInt($value, $validator)
    {
        if(!is_int($value))
        {
            // TODO - LOG ERROR: Type should be int
            return false;
        }

        foreach($validator["value"] as $valueChecker)
        {
            switch($valueChecker->type)
            {
                case "range":
                    break;
                default:
                    // TODO - LOG WARNING: invalid value check for int
            }
        }
    }

    /**
     * @param double $value The instance value of the particular property
     * @param array $validator The validator metadata (for double types) as defined by the class
     * @return bool Whether or not the value passes the validation
     */
    protected function _checkDouble($value, $validator)
    {
        if(!is_double($value))
        {
            // TODO - LOG ERROR: Type should be float
            return false;
        }

        foreach($validator["value"] as $type => $data)
        {
            switch($type)
            {
                case "range":
                    break;
                default:
                    // TODO - LOG WARNING: invalid value check for float
            }
        }
    }

    /**
     * @param string $value The instance value of the particular property
     * @param array $validator The validator metadata (for string types) as defined by the class
     * @return bool Whether or not the value passes the validation
     */
    protected function _checkString($value, $validator)
    {
        if(!is_string($value))
        {
            // TODO - LOG ERROR: Type should be string
            return false;
        }

        foreach($validator["value"] as $valueChecker)
        {
            switch($valueChecker->type)
            {
                case "length":
                    break;
                default:
                    // TODO - LOG WARNING: invalid value check for int
            }
        }
    }

    /**
     * Validator for "enum" types. PHP does not have enums, so it's really just strings with constant set of
     * potential values.
     * @param string $value The instance value of the particular property
     * @param array $validator The validator metadata (for enum types) as defined by the class
     * @return bool Whether or not the value passes the validation
     */
    protected function _checkEnum($value, $validator)
    {
        if(!is_string($value))
        {
            // TODO - LOG ERROR: Type should be string
            return false;
        }

        // enum MUST have a checker for value set
        if($validator["value"]["enum_set"] == null)
        {
            // TODO - LOG ERROR: enum must have valid set of values
            return false;
        }

        foreach($validator["value"] as $valueChecker)
        {
            switch($valueChecker->type)
            {
                case "enum_set":
                    break;
                default:
                    // TODO - LOG WARNING: invalid value check for enum
            }
        }
    }

    /**
     * @param object $value The instance value of the particular property
     * @param array $validator The validator metadata (for model-derived class types) as defined by the class
     * @return bool Whether or not the value passes the validation
     */
    protected function _checkSignifydModel($value, $validator)
    {
        if(!is_subclass_of($value, "SignifydModel"))
        {
            // TODO - LOG ERROR: Type should be subclass of SignifydModel
            return false;
        }

        foreach($validator["value"] as $valueChecker)
        {
            switch($valueChecker->type)
            {
                case "subtype":
                    break;
                default:
                    // TODO - LOG WARNING: invalid value check for SignifydModel
            }
        }
    }

    /**
     * @param object $value The instance value of the particular property
     * @param array $validator The validator metadata (for DateTime types) as defined by the class
     * @return bool Whether or not the value passes the validation
     */
    protected function _checkDateTime($value, $validator)
    {
        if(! date($value))
        {
            // TODO - LOG ERROR: Type should be int
            return false;
        }

        foreach($validator["value"] as $valueChecker)
        {
            switch($valueChecker->type)
            {
                case "range":
                    break;
                default:
                    // TODO - LOG WARNING: invalid value check for int
            }
        }
    }

    /**
     * Validate a bool property. Really, this is just verifying that the property is a bool.
     * @param bool $value The instance value of the particular property
     * @param array $validator Unused
     * @return bool Whether or not the value passes the validation
     */
    protected function _checkBool($value, $validator)
    {
        if(!is_bool($value))
        {
            // TODO - LOG ERROR: Type should be int
            return false;
        }
        return true;
    }
}
