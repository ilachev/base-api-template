<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

use App\Api\V1\User as User1;
use App\Domain\User\User;

final class UserProtoHydrator
{
    /**
     * Convert User message to User entity.
     *
     * @param User $proto Proto message
     * @return User Domain entity
     */
    public function hydrate(\User1 $proto): User
    {
        $entity = new \App\Domain\User\User();

        $entity->setId($proto->getId());
        $entity->setPasswordHash($proto->getPassword_hash());
        $entity->setCreatedAt(new \DateTime($proto->getCreated_at()));
        $entity->setUpdatedAt(new \DateTime($proto->getUpdated_at()));

        return $entity;
    }

    /**
     * Convert User entity to User message.
     *
     * @param User $entity Domain entity
     * @return User Proto message
     */
    public function extract(User $entity): \User1
    {
        $proto = new \App\Api\V1\User();

        $proto->setId($entity->getId());
        $proto->setPassword_hash($entity->getPasswordHash());
        $proto->setCreated_at($entity->getCreatedAt()->format('c'));
        $proto->setUpdated_at($entity->getUpdatedAt()->format('c'));

        return $proto;
    }
}
