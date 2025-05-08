<?php

declare(strict_types=1);

namespace ProtoPhpGen\Protoc\Binary;

use ProtoPhpGen\Protoc\PluginResponse;
use ProtoPhpGen\Protoc\PluginResponseFile;

/**
 * Класс для сериализации ответа в бинарный формат протобаф.
 */
final class CodeGeneratorResponseWriter
{
    // Номера полей в CodeGeneratorResponse
    private const FIELD_ERROR = 1;
    private const FIELD_FILE = 15;

    // Номера полей в CodeGeneratorResponse.File
    private const FIELD_FILE_NAME = 1;
    private const FIELD_FILE_CONTENT = 15;

    // Типы проволоки (wire types)
    private const WIRE_TYPE_LENGTH_DELIMITED = 2;

    /**
     * Сериализует ответ в бинарный формат протобаф.
     *
     * @param PluginResponse $response Объект ответа
     * @return string Бинарные данные
     */
    public function serialize(PluginResponse $response): string
    {
        $writer = new ProtobufWriter();

        // Если есть ошибка, записываем её
        if ($response->hasError()) {
            $writer->writeTag(self::FIELD_ERROR, self::WIRE_TYPE_LENGTH_DELIMITED);
            $writer->writeString($response->getError() ?? '');
        }

        // Записываем файлы
        foreach ($response->getFiles() as $file) {
            $fileData = $this->serializeFile($file);
            $writer->writeTag(self::FIELD_FILE, self::WIRE_TYPE_LENGTH_DELIMITED);
            $writer->writeMessage($fileData);
        }

        return $writer->getData();
    }

    /**
     * Сериализует файл в бинарный формат протобаф.
     *
     * @param PluginResponseFile $file Объект файла
     * @return string Бинарные данные
     */
    private function serializeFile(PluginResponseFile $file): string
    {
        $writer = new ProtobufWriter();

        // Записываем имя файла
        $writer->writeTag(self::FIELD_FILE_NAME, self::WIRE_TYPE_LENGTH_DELIMITED);
        $writer->writeString($file->getName());

        // Записываем содержимое файла
        $writer->writeTag(self::FIELD_FILE_CONTENT, self::WIRE_TYPE_LENGTH_DELIMITED);
        $writer->writeString($file->getContent());

        return $writer->getData();
    }
}
