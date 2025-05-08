<?php

declare(strict_types=1);

namespace ProtoPhpGen\Protoc\Binary;

/**
 * Класс для чтения бинарных данных протобаф.
 * Реализует базовый функционал для парсинга протобаф сообщений.
 */
final class ProtobufReader
{
    /**
     * @var string Бинарные данные
     */
    private string $data;

    /**
     * @var int Текущая позиция в данных
     */
    private int $position = 0;

    /**
     * @var int Размер данных
     */
    private int $size;

    /**
     * Конструктор.
     *
     * @param string $data Бинарные данные
     */
    public function __construct(string $data)
    {
        $this->data = $data;
        $this->size = \strlen($data);
    }

    /**
     * Проверяет, есть ли ещё данные для чтения.
     */
    public function hasMore(): bool
    {
        return $this->position < $this->size;
    }

    /**
     * Считывает тег поля (номер поля и тип).
     * Возвращает массив [field_number, wire_type].
     *
     * @return array{0: int, 1: int}
     */
    public function readTag(): array
    {
        $value = $this->readVarint();
        $wireType = $value & 0x07;
        $fieldNumber = $value >> 3;

        return [$fieldNumber, $wireType];
    }

    /**
     * Считывает varint (переменной длины целое число).
     */
    public function readVarint(): int
    {
        $value = 0;
        $shift = 0;

        do {
            if ($this->position >= $this->size) {
                throw new \RuntimeException('Unexpected end of data while reading varint');
            }

            $byte = \ord($this->data[$this->position++]);
            $value |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte & 0x80);

        return $value;
    }

    /**
     * Считывает 64-битное целое число.
     */
    public function readFixed64(): int
    {
        if ($this->position + 8 > $this->size) {
            throw new \RuntimeException('Unexpected end of data while reading fixed64');
        }

        $result = unpack('P', substr($this->data, $this->position, 8));

        if ($result === false) {
            throw new \RuntimeException('Failed to unpack fixed64 value');
        }

        $value = $result[1];
        $this->position += 8;

        return $value;
    }

    /**
     * Считывает 32-битное целое число.
     */
    public function readFixed32(): int
    {
        if ($this->position + 4 > $this->size) {
            throw new \RuntimeException('Unexpected end of data while reading fixed32');
        }

        $result = unpack('V', substr($this->data, $this->position, 4));

        if ($result === false) {
            throw new \RuntimeException('Failed to unpack fixed32 value');
        }

        $value = $result[1];
        $this->position += 4;

        return $value;
    }

    /**
     * Считывает строку.
     */
    public function readString(): string
    {
        $length = $this->readVarint();

        if ($this->position + $length > $this->size) {
            throw new \RuntimeException('Unexpected end of data while reading string');
        }

        $value = substr($this->data, $this->position, $length);
        $this->position += $length;

        return $value;
    }

    /**
     * Считывает вложенное сообщение.
     */
    public function readMessage(): string
    {
        $length = $this->readVarint();

        if ($this->position + $length > $this->size) {
            throw new \RuntimeException('Unexpected end of data while reading message');
        }

        $value = substr($this->data, $this->position, $length);
        $this->position += $length;

        return $value;
    }

    /**
     * Пропускает поле с заданным типом проволоки.
     */
    public function skipField(int $wireType): void
    {
        switch ($wireType) {
            case 0: // Varint
                $this->readVarint();
                break;

            case 1: // 64-bit
                $this->position += 8;
                break;

            case 2: // Length-delimited
                $length = $this->readVarint();
                $this->position += $length;
                break;

            case 5: // 32-bit
                $this->position += 4;
                break;

            default:
                throw new \RuntimeException("Unknown wire type: {$wireType}");
        }
    }
}
