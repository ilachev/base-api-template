<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

use App\Domain\Session\SessionPayload;

final class SessionPayloadProtoHydrator
{
    /**
     * Convert SessionPayload message to SessionPayload entity.
     *
     * @param SessionPayload $proto Proto message
     * @return SessionPayload Domain entity
     */
    public function hydrate(SessionPayload $proto): SessionPayload
    {
        $entity = new \App\Domain\Session\SessionPayload();

        $entity->setIp($proto->getIp());
        $entity->setUserAgent($proto->getUser_agent());
        $entity->setAcceptLanguage($proto->getAccept_language());
        $entity->setAcceptEncoding($proto->getAccept_encoding());
        $entity->setXForwardedFor($proto->getX_forwarded_for());
        $entity->setReferer($proto->getReferer());
        $entity->setOrigin($proto->getOrigin());
        $entity->setSecChUa($proto->getSec_ch_ua());
        $entity->setSecChUaPlatform($proto->getSec_ch_ua_platform());
        $entity->setSecChUaMobile($proto->getSec_ch_ua_mobile());
        $entity->setDnt($proto->getDnt());
        $entity->setSecFetchDest($proto->getSec_fetch_dest());
        $entity->setSecFetchMode($proto->getSec_fetch_mode());
        $entity->setSecFetchSite($proto->getSec_fetch_site());
        $entity->setGeoLocation(json_decode($proto->getGeo_location(), true));

        return $entity;
    }

    /**
     * Convert SessionPayload entity to SessionPayload message.
     *
     * @param SessionPayload $entity Domain entity
     * @return SessionPayload Proto message
     */
    public function extract(SessionPayload $entity): SessionPayload
    {
        $proto = new \App\Domain\Session\SessionPayload();

        $proto->setIp($entity->getIp());
        $proto->setUser_agent($entity->getUserAgent());
        $proto->setAccept_language($entity->getAcceptLanguage());
        $proto->setAccept_encoding($entity->getAcceptEncoding());
        $proto->setX_forwarded_for($entity->getXForwardedFor());
        $proto->setReferer($entity->getReferer());
        $proto->setOrigin($entity->getOrigin());
        $proto->setSec_ch_ua($entity->getSecChUa());
        $proto->setSec_ch_ua_platform($entity->getSecChUaPlatform());
        $proto->setSec_ch_ua_mobile($entity->getSecChUaMobile());
        $proto->setDnt($entity->getDnt());
        $proto->setSec_fetch_dest($entity->getSecFetchDest());
        $proto->setSec_fetch_mode($entity->getSecFetchMode());
        $proto->setSec_fetch_site($entity->getSecFetchSite());
        $proto->setGeo_location(json_encode($entity->getGeoLocation()));

        return $proto;
    }
}
