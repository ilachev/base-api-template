<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

final class SetterProtobufHydration implements ProtobufHydration
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array<string, mixed> $data
     * @return T
     */
    public function hydrate(string $className, array $data): object
    {
        /** @var T $instance */
        $instance = new $className();

        foreach ($data as $field => $value) {
            $setter = 'set' . ucfirst($field);
            if (method_exists($instance, $setter)) {
                $instance->{$setter}($value);
            }
        }

        return $instance;
    }
}
