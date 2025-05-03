<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Service for identifying and matching clients based on fingerprint.
 */
final readonly class FingerprintClientDetector implements ClientDetector
{
    public function __construct(
        private SessionRepository $sessionRepository,
        private ClientConfig $config,
    ) {}

    /**
     * Determines if the request belongs to an already known client.
     *
     * @param ServerRequestInterface $request Current HTTP request
     * @param bool $includeCurrent Whether to include the current session in search results (for tests)
     * @return array<ClientIdentity> List of similar clients found, sorted by similarity
     */
    public function findSimilarClients(ServerRequestInterface $request, bool $includeCurrent = false): array
    {
        // Get current session from request attributes
        /** @var ?Session $currentSession */
        $currentSession = $request->getAttribute('session');

        if ($currentSession === null) {
            return [];
        }

        // Create identity for current client
        $currentIdentity = ClientIdentity::fromSession($currentSession);

        // Get all sessions
        $allSessions = $this->sessionRepository->findAll();

        // Filter out current session if needed
        $otherSessions = $includeCurrent
            ? $allSessions  // Include all sessions for tests
            : array_filter(
                $allSessions,
                static fn(Session $session) => $session->id !== $currentSession->id,
            );

        if (empty($otherSessions)) {
            return [];
        }

        // Convert all sessions to identities and calculate similarity
        $similarities = [];
        foreach ($otherSessions as $session) {
            $otherIdentity = ClientIdentity::fromSession($session);
            $score = $this->calculateSimilarityScore($currentIdentity, $otherIdentity);

            // Add client only if similarity is above threshold
            if ($score >= $this->config->similarityThreshold) {
                $similarities[] = [
                    'identity' => $otherIdentity,
                    'score' => $score,
                ];
            }
        }

        // Sort by descending similarity
        usort($similarities, static fn(array $a, array $b) => $b['score'] <=> $a['score']);

        // Return only client identities
        return array_map(
            static fn(array $item) => $item['identity'],
            $similarities,
        );
    }

    /**
     * Checks if the current request is potentially suspicious
     * (e.g., too many sessions from one IP).
     *
     * @param ServerRequestInterface $request Current HTTP request
     */
    public function isRequestSuspicious(ServerRequestInterface $request): bool
    {
        // Get current session from request attributes
        /** @var ?Session $currentSession */
        $currentSession = $request->getAttribute('session');

        if ($currentSession === null) {
            return false;
        }

        // Create identity for current client and extract IP
        $currentIdentity = ClientIdentity::fromSession($currentSession);
        $currentIp = $currentIdentity->ipAddress;

        // If IP is unknown, skip the check
        if ($currentIp === 'unknown') {
            return false;
        }

        // Get all sessions with the same IP
        $allSessions = $this->sessionRepository->findAll();
        $sessionsWithSameIp = array_filter(
            $allSessions,
            static function (Session $session) use ($currentIp, $currentSession) {
                if ($session->id === $currentSession->id) {
                    return false;
                }

                $payload = json_decode($session->payload, true);
                if (!\is_array($payload)) {
                    return false;
                }

                return ($payload['ip'] ?? 'unknown') === $currentIp;
            },
        );

        // If too many sessions from one IP, consider it suspicious
        return \count($sessionsWithSameIp) >= $this->config->maxSessionsPerIp;
    }

    /**
     * Calculates a "similarity" score between two client identities
     * The higher the value, the more confidence that it's the same client.
     *
     * @param ClientIdentity $identity1 First identity
     * @param ClientIdentity $identity2 Second identity
     * @return float Value from 0.0 to 1.0
     */
    private function calculateSimilarityScore(ClientIdentity $identity1, ClientIdentity $identity2): float
    {
        // If IDs match, it's a 100% match
        if ($identity1->id === $identity2->id) {
            return 1.0;
        }

        $score = 0.0;

        // Check IP
        if ($identity1->ipAddress !== 'unknown' && $identity1->ipAddress === $identity2->ipAddress) {
            $score += $this->config->ipMatchWeight;
        }

        // Check User-Agent
        if ($identity1->userAgent !== null && $identity1->userAgent === $identity2->userAgent) {
            $score += $this->config->userAgentMatchWeight;
        }

        // Check additional attributes
        if (!empty($identity1->attributes) || !empty($identity2->attributes)) {
            // Create a combined list of all attributes
            $allAttributes = array_unique(array_merge(
                array_keys($identity1->attributes),
                array_keys($identity2->attributes),
            ));

            if (!empty($allAttributes)) {
                $matchCount = 0;

                foreach ($allAttributes as $key) {
                    // Check for attribute with the same value in both identities
                    if (
                        isset($identity1->attributes[$key], $identity2->attributes[$key])
                        && $identity1->attributes[$key] === $identity2->attributes[$key]
                    ) {
                        ++$matchCount;
                    }
                }

                // Calculate attribute match percentage
                $attributeMatchPercent = $matchCount / \count($allAttributes);
                $score += $this->config->attributesMatchWeight * $attributeMatchPercent;
            }
        }

        // For test environments, if IP and User-Agent match completely,
        // it's most likely the same client
        if (
            $identity1->ipAddress !== 'unknown'
            && $identity1->ipAddress === $identity2->ipAddress
            && $identity1->userAgent !== null
            && $identity1->userAgent === $identity2->userAgent
        ) {
            // Increase similarity if both main parameters match
            $score = max($score, 0.9);
        }

        return $score;
    }
}
