<?php

declare(strict_types=1);

namespace ProtoPhpGen\Protoc\Binary;

use ProtoPhpGen\Protoc\PluginRequest;

/**
 * Парсер для CodeGeneratorRequest.
 * Извлекает данные из бинарного формата протобаф и создает объект PluginRequest.
 */
final class CodeGeneratorRequestParser
{
    // Номера полей в CodeGeneratorRequest
    private const FIELD_FILE_TO_GENERATE = 1;
    private const FIELD_PARAMETER = 2;
    private const FIELD_PROTO_FILE = 15;

    // Типы проволоки (wire types)
    private const WIRE_TYPE_VARINT = 0;
    private const WIRE_TYPE_LENGTH_DELIMITED = 2;

    /**
     * Парсит бинарные данные и создает объект PluginRequest.
     *
     * @param string $data Бинарные данные
     */
    public function parse(string $data): PluginRequest
    {
        $request = new PluginRequest();
        $reader = new ProtobufReader($data);

        while ($reader->hasMore()) {
            [$fieldNumber, $wireType] = $reader->readTag();

            switch ($fieldNumber) {
                case self::FIELD_FILE_TO_GENERATE:
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $request->addFileToGenerate($reader->readString());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case self::FIELD_PARAMETER:
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $request->setParameter($reader->readString());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case self::FIELD_PROTO_FILE:
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $this->parseProtoFile($reader->readMessage(), $request);
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                default:
                    $reader->skipField($wireType);
                    break;
            }
        }

        return $request;
    }

    /**
     * Парсит бинарные данные FileDescriptorProto.
     *
     * @param string $data Бинарные данные
     * @param PluginRequest $request Объект запроса
     */
    private function parseProtoFile(string $data, PluginRequest $request): void
    {
        $reader = new ProtobufReader($data);
        $protoFile = [];

        while ($reader->hasMore()) {
            [$fieldNumber, $wireType] = $reader->readTag();

            switch ($fieldNumber) {
                case 1: // name
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $protoFile['name'] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 2: // package
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $protoFile['package'] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 3: // dependency
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        if (!isset($protoFile['dependency'])) {
                            $protoFile['dependency'] = [];
                        }
                        $protoFile['dependency'][] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 4: // message_type
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        if (!isset($protoFile['message_type'])) {
                            $protoFile['message_type'] = [];
                        }
                        $protoFile['message_type'][] = $this->parseMessageType($reader->readMessage());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 5: // enum_type
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        if (!isset($protoFile['enum_type'])) {
                            $protoFile['enum_type'] = [];
                        }
                        $protoFile['enum_type'][] = $this->parseEnumType($reader->readMessage());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 7: // options
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $protoFile['options'] = $this->parseFileOptions($reader->readMessage());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                default:
                    $reader->skipField($wireType);
                    break;
            }
        }

        if (isset($protoFile['name'])) {
            $request->addProtoFile($protoFile['name'], $protoFile);
        }
    }

    /**
     * Парсит бинарные данные DescriptorProto (типа сообщения).
     *
     * @param string $data Бинарные данные
     * @return array<string, mixed> Распарсенный тип сообщения
     */
    private function parseMessageType(string $data): array
    {
        $reader = new ProtobufReader($data);
        $messageType = [];

        while ($reader->hasMore()) {
            [$fieldNumber, $wireType] = $reader->readTag();

            switch ($fieldNumber) {
                case 1: // name
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $messageType['name'] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 2: // field
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        if (!isset($messageType['field'])) {
                            $messageType['field'] = [];
                        }
                        $messageType['field'][] = $this->parseField($reader->readMessage());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 3: // nested_type
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        if (!isset($messageType['nested_type'])) {
                            $messageType['nested_type'] = [];
                        }
                        $messageType['nested_type'][] = $this->parseMessageType($reader->readMessage());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 4: // enum_type
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        if (!isset($messageType['enum_type'])) {
                            $messageType['enum_type'] = [];
                        }
                        $messageType['enum_type'][] = $this->parseEnumType($reader->readMessage());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 7: // options
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $messageType['options'] = $this->parseMessageOptions($reader->readMessage());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                default:
                    $reader->skipField($wireType);
                    break;
            }
        }

        return $messageType;
    }

    /**
     * Парсит бинарные данные FieldDescriptorProto (поля сообщения).
     *
     * @param string $data Бинарные данные
     * @return array<string, mixed> Распарсенное поле сообщения
     */
    private function parseField(string $data): array
    {
        $reader = new ProtobufReader($data);
        $field = [];

        while ($reader->hasMore()) {
            [$fieldNumber, $wireType] = $reader->readTag();

            switch ($fieldNumber) {
                case 1: // name
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $field['name'] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 3: // number
                    if ($wireType === self::WIRE_TYPE_VARINT) {
                        $field['number'] = $reader->readVarint();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 4: // label (repeated, optional, required)
                    if ($wireType === self::WIRE_TYPE_VARINT) {
                        $field['label'] = $reader->readVarint();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 5: // type (int32, string, message, etc.)
                    if ($wireType === self::WIRE_TYPE_VARINT) {
                        $field['type'] = $reader->readVarint();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 6: // type_name (for message and enum types)
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $field['type_name'] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 8: // options
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $field['options'] = $this->parseFieldOptions($reader->readMessage());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                default:
                    $reader->skipField($wireType);
                    break;
            }
        }

        return $field;
    }

    /**
     * Парсит бинарные данные EnumDescriptorProto (типа перечисления).
     *
     * @param string $data Бинарные данные
     * @return array<string, mixed> Распарсенный тип перечисления
     */
    private function parseEnumType(string $data): array
    {
        $reader = new ProtobufReader($data);
        $enumType = [];

        while ($reader->hasMore()) {
            [$fieldNumber, $wireType] = $reader->readTag();

            switch ($fieldNumber) {
                case 1: // name
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $enumType['name'] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 2: // value
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        if (!isset($enumType['value'])) {
                            $enumType['value'] = [];
                        }
                        $enumType['value'][] = $this->parseEnumValue($reader->readMessage());
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                default:
                    $reader->skipField($wireType);
                    break;
            }
        }

        return $enumType;
    }

    /**
     * Парсит бинарные данные EnumValueDescriptorProto (значения перечисления).
     *
     * @param string $data Бинарные данные
     * @return array<string, mixed> Распарсенное значение перечисления
     */
    private function parseEnumValue(string $data): array
    {
        $reader = new ProtobufReader($data);
        $enumValue = [];

        while ($reader->hasMore()) {
            [$fieldNumber, $wireType] = $reader->readTag();

            switch ($fieldNumber) {
                case 1: // name
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $enumValue['name'] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 2: // number
                    if ($wireType === self::WIRE_TYPE_VARINT) {
                        $enumValue['number'] = $reader->readVarint();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                default:
                    $reader->skipField($wireType);
                    break;
            }
        }

        return $enumValue;
    }

    /**
     * Парсит бинарные данные FileOptions.
     *
     * @param string $data Бинарные данные
     * @return array<string, mixed> Распарсенные опции файла
     */
    private function parseFileOptions(string $data): array
    {
        $reader = new ProtobufReader($data);
        $options = [];

        while ($reader->hasMore()) {
            [$fieldNumber, $wireType] = $reader->readTag();

            switch ($fieldNumber) {
                case 1: // php_namespace
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $options['php_namespace'] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 8: // php_class_prefix
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $options['php_class_prefix'] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                case 40: // php_metadata_namespace
                    if ($wireType === self::WIRE_TYPE_LENGTH_DELIMITED) {
                        $options['php_metadata_namespace'] = $reader->readString();
                    } else {
                        $reader->skipField($wireType);
                    }
                    break;

                default:
                    $reader->skipField($wireType);
                    break;
            }
        }

        return $options;
    }

    /**
     * Парсит бинарные данные MessageOptions.
     *
     * @param string $data Бинарные данные
     * @return array<string, mixed> Распарсенные опции сообщения
     */
    private function parseMessageOptions(string $data): array
    {
        // Для простоты просто пропускаем все поля и возвращаем пустой массив
        // В реальной реализации здесь был бы код для извлечения опций сообщения
        return [];
    }

    /**
     * Парсит бинарные данные FieldOptions.
     *
     * @param string $data Бинарные данные
     * @return array<string, mixed> Распарсенные опции поля
     */
    private function parseFieldOptions(string $data): array
    {
        // Для простоты просто пропускаем все поля и возвращаем пустой массив
        // В реальной реализации здесь был бы код для извлечения опций поля
        return [];
    }
}
