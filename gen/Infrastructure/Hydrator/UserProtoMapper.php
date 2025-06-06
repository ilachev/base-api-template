<?php

/**
 * This file is auto-generated. DO NOT EDIT.
 *
 * Generated by protoc-php-gen
 */

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

use App\Api\V1\User as V1User;
use App\Domain\User\User as UserUser;

final class UserProtoMapper
{
    /**
     * Convert User message to User entity.
     *
     * @param V1User $proto Proto message
     * @return UserUser Domain entity
     */
    public function hydrate(\V1User $proto): \UserUser
    {
        return new UserUser(
            $proto->getId(), // id,
            $proto->getPasswordHash(), // passwordHash,
            new \DateTime('@' . $proto->getCreatedAt()), // createdAt,
            new \DateTime('@' . $proto->getUpdatedAt()), // updatedAt
        );
    }

    /**
     * Convert User entity to User message.
     *
     * @param UserUser $entity Domain entity
     * @return V1User Proto message
     */
    public function extract(\UserUser $entity): \V1User
    {
        $proto = new V1User();

        $proto->setId($entity->id);
        $proto->setPasswordHash($entity->passwordHash);
        $proto->setCreatedAt($entity->createdAt instanceof \DateTimeInterface ? $entity->createdAt->getTimestamp() : 0);
        $proto->setUpdatedAt($entity->updatedAt instanceof \DateTimeInterface ? $entity->updatedAt->getTimestamp() : 0);

        return $proto;
    }
}
