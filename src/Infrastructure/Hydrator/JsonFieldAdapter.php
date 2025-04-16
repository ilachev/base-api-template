<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

/**
 * Интерфейс адаптера для работы с JSON-полями в базе данных.
 */
interface JsonFieldAdapter
{
    /**
     * Преобразует значение поля JSON в объект
     *
     * @param string $jsonValue JSON строка из базы данных
     * @param string $targetClass Класс для гидрации
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer Функция для трансформации полей
     * @throws HydratorException Если не удалось гидрировать объект
     */
    public function deserialize(
        string $jsonValue,
        string $targetClass,
        ?callable $fieldTransformer = null,
    ): object;

    /**
     * Преобразует объект в JSON строку для хранения в базе данных.
     *
     * @param object $object Объект для сериализации
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer Функция для трансформации полей
     * @throws HydratorException Если не удалось сериализовать объект
     */
    public function serialize(object $object, ?callable $fieldTransformer = null): string;

    /**
     * Пытается десериализовать JSON, но в случае ошибки возвращает значение по умолчанию.
     *
     * @param string $jsonValue JSON строка из базы данных
     * @param string $targetClass Класс для гидрации
     * @param object $defaultValue Значение по умолчанию при ошибке
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer Функция для трансформации полей
     */
    public function tryDeserialize(
        string $jsonValue,
        string $targetClass,
        object $defaultValue,
        ?callable $fieldTransformer = null,
    ): object;

    /**
     * Пытается сериализовать объект, но в случае ошибки возвращает значение по умолчанию.
     *
     * @param object $object Объект для сериализации
     * @param string $defaultJson JSON по умолчанию при ошибке
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer Функция для трансформации полей
     */
    public function trySerialize(
        object $object,
        string $defaultJson = '{}',
        ?callable $fieldTransformer = null,
    ): string;
}
