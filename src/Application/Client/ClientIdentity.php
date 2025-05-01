<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Session\Session;

/**
 * Represents client identification data extracted from fingerprint.
 */
final readonly class ClientIdentity
{
    /**
     * @param string $id Unique client identifier
     * @param string $ipAddress Client IP address
     * @param string|null $userAgent Client User-Agent header
     * @param array<string, string> $attributes Additional client attributes
     */
    public function __construct(
        public string $id,
        public string $ipAddress,
        public ?string $userAgent = null,
        public array $attributes = [],
    ) {}

    /**
     * Creates a ClientIdentity object from session data.
     *
     * @param Session $session The session from which to extract data
     */
    public static function fromSession(Session $session): self
    {
        $payload = json_decode($session->payload, true);

        if (!\is_array($payload)) {
            // If we can't parse the payload, create an empty identifier
            return new self(
                id: $session->id,
                ipAddress: 'unknown',
            );
        }

        // Extract IP address from payload
        $ipAddress = 'unknown';
        if (isset($payload['ip']) && \is_string($payload['ip'])) {
            $ipAddress = $payload['ip'];
        }

        // Extract User-Agent
        $userAgent = null;
        if (isset($payload['userAgent']) && \is_string($payload['userAgent'])) {
            $userAgent = $payload['userAgent'];
        }

        // Extract additional attributes for matching
        /** @var array<string, string> $attributes */
        $attributes = [];

        // Add attributes from SessionPayload fields
        $attributeFields = [
            'acceptLanguage', 'acceptEncoding', 'xForwardedFor', 'referer',
            'origin', 'secChUa', 'secChUaPlatform', 'secChUaMobile',
            'dnt', 'secFetchDest', 'secFetchMode', 'secFetchSite',
        ];

        foreach ($attributeFields as $field) {
            if (isset($payload[$field]) && \is_string($payload[$field])) {
                $attributes[$field] = $payload[$field];
            }
        }

        return new self(
            id: $session->id,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            attributes: $attributes,
        );
    }

    /**
     * Checks if this identity matches another identity.
     *
     * @param self $other Other identity to compare with
     * @param bool $strictIpMatch Whether to require strict IP matching
     */
    public function matches(self $other, bool $strictIpMatch = false): bool
    {
        // If IDs match, it's definitely the same client
        if ($this->id === $other->id) {
            return true;
        }

        // Check IP address
        $ipMatches = $strictIpMatch
            ? $this->ipAddress === $other->ipAddress
            : $this->ipAddress !== 'unknown' && $this->ipAddress === $other->ipAddress;

        // Check User-Agent
        $uaMatches = $this->userAgent !== null && $this->userAgent === $other->userAgent;

        // Minimum match criteria: either IP + User-Agent, or IP + at least one attribute
        return ($ipMatches && $uaMatches)
               || ($ipMatches && \count(array_intersect_assoc($this->attributes, $other->attributes)) > 0);
    }
}
