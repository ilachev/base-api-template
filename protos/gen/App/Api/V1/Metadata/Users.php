<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: app/v1/users.proto

namespace App\Api\V1\Metadata;

class Users
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();
        if (static::$is_initialized == true) {
          return;
        }
        $pool->internalAddGeneratedFile(
            "\x0A\xA8\x01\x0A\x12app/v1/users.proto\x12\x06app.v1\"Q\x0A\x04User\x12\x0A\x0A\x02id\x18\x01 \x01(\x03\x12\x15\x0A\x0Dpassword_hash\x18\x02 \x01(\x09\x12\x12\x0A\x0Acreated_at\x18\x03 \x01(\x03\x12\x12\x0A\x0Aupdated_at\x18\x04 \x01(\x03B/Z\x0Aapp/api/v1\xCA\x02\x0AApp\\Api\\V1\xE2\x02\x13App\\Api\\V1\\Metadatab\x06proto3"
        , true);
        static::$is_initialized = true;
    }
}

