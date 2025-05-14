<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

use App\Domain\Session\Session;

final class SessionProtoHydrator
{
    /**
     * Convert Session message to Session entity.
     *
     * @param Session $proto Proto message
     * @return Session Domain entity
     */
    public function hydrate(Session $proto): Session
    {
        $entity = new \App\Domain\Session\Session();

        $entity->setId($proto->getId());
        $entity->setUserId($proto->getUser_id());
        $entity->setPayload(json_decode($proto->getPayload(), true));
        $entity->setExpiresAt(new \DateTime($proto->getExpires_at()));
        $entity->setCreatedAt(new \DateTime($proto->getCreated_at()));
        $entity->setUpdatedAt(new \DateTime($proto->getUpdated_at()));

        return $entity;
    }

    /**
     * Convert Session entity to Session message.
     *
     * @param Session $entity Domain entity
     * @return Session Proto message
     */
    public function extract(Session $entity): Session
    {
        $proto = new \App\Domain\Session\Session();

        $proto->setId($entity->getId());
        $proto->setUser_id($entity->getUserId());
        $proto->setPayload(json_encode($entity->getPayload()));
        $proto->setExpires_at($entity->getExpiresAt()->format('c'));
        $proto->setCreated_at($entity->getCreatedAt()->format('c'));
        $proto->setUpdated_at($entity->getUpdatedAt()->format('c'));

        return $proto;
    }
}
