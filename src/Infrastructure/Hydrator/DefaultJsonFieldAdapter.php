<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

/**
 * Адаптер для работы с JSON-полями в базе данных
 * Предоставляет функциональность для преобразования между JSON строками и объектами PHP.
 */
final readonly class DefaultJsonFieldAdapter implements JsonFieldAdapter
{
    public function __construct(
        private Hydrator $hydrator,
    ) {}

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
    ): object {
        $data = json_decode($jsonValue, true);

        if (!\is_array($data)) {
            throw new HydratorException("Invalid JSON data for {$targetClass}");
        }

        /** @var array<string, mixed> $typedData */
        $typedData = $data;

        if ($fieldTransformer !== null) {
            $typedData = $fieldTransformer($typedData);
        }

        // Проверяем, что класс существует перед гидрацией
        if (!class_exists($targetClass)) {
            throw new HydratorException("Target class {$targetClass} does not exist");
        }

        /** @var class-string<object> $validClass */
        $validClass = $targetClass;
        $result = $this->hydrator->hydrate($validClass, $typedData);

        return $result;
    }

    /**
     * Преобразует объект в JSON строку для хранения в базе данных.
     *
     * @param object $object Объект для сериализации
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $fieldTransformer Функция для трансформации полей
     * @throws HydratorException Если не удалось сериализовать объект
     */
    public function serialize(object $object, ?callable $fieldTransformer = null): string
    {
        $data = $this->hydrator->extract($object);

        /** @var array<string, mixed> $typedData */
        $typedData = $data;

        if ($fieldTransformer !== null) {
            $typedData = $fieldTransformer($typedData);
        }

        $json = json_encode($typedData);

        if ($json === false) {
            throw new HydratorException(
                'Failed to encode object to JSON: ' . json_last_error_msg(),
            );
        }

        return $json;
    }

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
    ): object {
        try {
            return $this->deserialize($jsonValue, $targetClass, $fieldTransformer);
        } catch (HydratorException $e) {
            return $defaultValue;
        }
    }

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
    ): string {
        try {
            return $this->serialize($object, $fieldTransformer);
        } catch (HydratorException $e) {
            return $defaultJson;
        }
    }
}
