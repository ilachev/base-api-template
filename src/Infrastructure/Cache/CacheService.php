<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

interface CacheService
{
    /**
     * Проверяет, доступен ли кеш-сервис.
     */
    public function isAvailable(): bool;

    /**
     * Сохраняет значение в кеше.
     *
     * @template T
     * @param string $key Ключ кеша
     * @param T $value Значение для сохранения
     * @param int|null $ttl Время жизни в секундах (null - без ограничения)
     * @return bool Результат операции
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Получает значение из кеша.
     *
     * @param string $key Ключ кеша
     * @param mixed $default Значение по умолчанию
     * @return mixed Значение из кеша или значение по умолчанию
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Проверяет существование ключа в кеше.
     *
     * @param string $key Ключ кеша
     * @return bool Существует ли ключ в кеше
     */
    public function has(string $key): bool;

    /**
     * Удаляет значение из кеша.
     *
     * @param string $key Ключ кеша
     * @return bool Результат операции
     */
    public function delete(string $key): bool;

    /**
     * Очищает весь кеш.
     *
     * @return bool Результат операции
     */
    public function clear(): bool;

    /**
     * Получает или вычисляет значение для ключа.
     *
     * @template T of mixed
     * @param string $key Ключ кеша
     * @param callable():T $callback Функция для вычисления значения
     * @param int|null $ttl Время жизни в секундах (null - без ограничения)
     * @return T Значение из кеша или вычисленное значение
     */
    public function getOrSet(string $key, callable $callback, ?int $ttl = null): mixed;
}
