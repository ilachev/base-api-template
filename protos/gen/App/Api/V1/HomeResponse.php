<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: app/v1/home.proto

namespace App\Api\V1;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * HomeResponse ответ с данными домашней страницы
 *
 * Generated from protobuf message <code>app.v1.HomeResponse</code>
 */
class HomeResponse extends \Google\Protobuf\Internal\Message
{
    /**
     * Данные домашней страницы
     *
     * Generated from protobuf field <code>.app.v1.HomeData data = 1 [(.grpc.gateway.protoc_gen_openapiv2.options.openapiv2_field) = {</code>
     */
    protected $data = null;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type \App\Api\V1\HomeData $data
     *           Данные домашней страницы
     * }
     */
    public function __construct($data = NULL) {
        \App\Api\V1\Metadata\Home::initOnce();
        parent::__construct($data);
    }

    /**
     * Данные домашней страницы
     *
     * Generated from protobuf field <code>.app.v1.HomeData data = 1 [(.grpc.gateway.protoc_gen_openapiv2.options.openapiv2_field) = {</code>
     * @return \App\Api\V1\HomeData|null
     */
    public function getData()
    {
        return $this->data;
    }

    public function hasData()
    {
        return isset($this->data);
    }

    public function clearData()
    {
        unset($this->data);
    }

    /**
     * Данные домашней страницы
     *
     * Generated from protobuf field <code>.app.v1.HomeData data = 1 [(.grpc.gateway.protoc_gen_openapiv2.options.openapiv2_field) = {</code>
     * @param \App\Api\V1\HomeData $var
     * @return $this
     */
    public function setData($var)
    {
        GPBUtil::checkMessage($var, \App\Api\V1\HomeData::class);
        $this->data = $var;

        return $this;
    }

}

