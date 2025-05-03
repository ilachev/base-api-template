<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

interface Hydrator
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @param mixed[] $data
     * @return T
     */
    public function hydrate(string $className, array $data): object;

    /**
     * @return mixed[]
     */
    public function extract(object $object): array;
}
