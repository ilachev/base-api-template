<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: app/v1/api.proto

namespace App\Api\V1;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Common structures
 *
 * Generated from protobuf message <code>app.v1.Error</code>
 */
class Error extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>string code = 1;</code>
     */
    protected $code = '';
    /**
     * Generated from protobuf field <code>string message = 2;</code>
     */
    protected $message = '';
    /**
     * Generated from protobuf field <code>map<string, string> details = 3;</code>
     */
    private $details;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $code
     *     @type string $message
     *     @type array|\Google\Protobuf\Internal\MapField $details
     * }
     */
    public function __construct($data = NULL) {
        \App\Api\V1\Metadata\Api::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>string code = 1;</code>
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Generated from protobuf field <code>string code = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setCode($var)
    {
        GPBUtil::checkString($var, True);
        $this->code = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string message = 2;</code>
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Generated from protobuf field <code>string message = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setMessage($var)
    {
        GPBUtil::checkString($var, True);
        $this->message = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>map<string, string> details = 3;</code>
     * @return \Google\Protobuf\Internal\MapField
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Generated from protobuf field <code>map<string, string> details = 3;</code>
     * @param array|\Google\Protobuf\Internal\MapField $var
     * @return $this
     */
    public function setDetails($var)
    {
        $arr = GPBUtil::checkMapField($var, \Google\Protobuf\Internal\GPBType::STRING, \Google\Protobuf\Internal\GPBType::STRING);
        $this->details = $arr;

        return $this;
    }

}
