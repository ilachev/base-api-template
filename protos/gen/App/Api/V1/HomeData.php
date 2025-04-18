<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: app/v1/home.proto

namespace App\Api\V1;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * HomeData содержит информацию для отображения на домашней странице
 *
 * Generated from protobuf message <code>app.v1.HomeData</code>
 */
class HomeData extends \Google\Protobuf\Internal\Message
{
    /**
     * Приветственное сообщение
     *
     * Generated from protobuf field <code>string message = 1 [(.grpc.gateway.protoc_gen_openapiv2.options.openapiv2_field) = {</code>
     */
    protected $message = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $message
     *           Приветственное сообщение
     * }
     */
    public function __construct($data = NULL) {
        \App\Api\V1\Metadata\Home::initOnce();
        parent::__construct($data);
    }

    /**
     * Приветственное сообщение
     *
     * Generated from protobuf field <code>string message = 1 [(.grpc.gateway.protoc_gen_openapiv2.options.openapiv2_field) = {</code>
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Приветственное сообщение
     *
     * Generated from protobuf field <code>string message = 1 [(.grpc.gateway.protoc_gen_openapiv2.options.openapiv2_field) = {</code>
     * @param string $var
     * @return $this
     */
    public function setMessage($var)
    {
        GPBUtil::checkString($var, True);
        $this->message = $var;

        return $this;
    }

}

