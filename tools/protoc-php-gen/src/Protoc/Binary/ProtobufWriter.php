<?php

declare(strict_types=1);

namespace ProtoPhpGen\Protoc\Binary;

/**
 * Класс для записи бинарных данных протобаф.
 * Реализует базовый функционал для сериализации протобаф сообщений.
 */
final class ProtobufWriter
{
    /**
     * @var string Бинарные данные
     */
    private string $data = '';

    /**
     * Записывает тег поля (номер поля и тип).
     */
    public function writeTag(int $fieldNumber, int $wireType): void
    {
        $this->writeVarint(($fieldNumber << 3) | $wireType);
    }

    /**
     * Записывает varint (переменной длины целое число).
     */
    public function writeVarint(int $value): void
    {
        do {
            $byte = $value & 0x7F;
            $value >>= 7;

            if ($value) {
                $byte |= 0x80;
            }

            $this->data .= \chr($byte);
        } while ($value);
    }

    /**
     * Записывает 64-битное целое число.
     */
    public function writeFixed64(int $value): void
    {
        $this->data .= pack('P', $value);
    }

    /**
     * Записывает 32-битное целое число.
     */
    public function writeFixed32(int $value): void
    {
        $this->data .= pack('V', $value);
    }

    /**
     * Записывает строку.
     */
    public function writeString(string $value): void
    {
        $this->writeVarint(\strlen($value));
        $this->data .= $value;
    }

    /**
     * Записывает вложенное сообщение.
     */
    public function writeMessage(string $value): void
    {
        $this->writeVarint(\strlen($value));
        $this->data .= $value;
    }

    /**
     * Возвращает сериализованные данные.
     */
    public function getData(): string
    {
        return $this->data;
    }
}
