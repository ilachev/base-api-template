<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Client\ClientDetectorInterface;
use App\Application\Client\SessionPayloadFactory;
use App\Application\Http\Middleware;
use App\Application\Http\RequestHandler;
use App\Domain\Session\Session;
use App\Domain\Session\SessionConfig;
use App\Domain\Session\SessionService;
use App\Infrastructure\Hydrator\JsonFieldAdapter;
use App\Infrastructure\Logger\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SessionMiddleware implements Middleware
{
    public function __construct(
        private SessionService $sessionService,
        private Logger $logger,
        private SessionConfig $config,
        private SessionPayloadFactory $sessionPayloadFactory,
        private JsonFieldAdapter $jsonAdapter,
        private ClientDetectorInterface $clientDetector,
    ) {}

    /**
     * Gets a dependency by name for testing.
     *
     * @param string $name Dependency name
     * @return mixed Dependency value or null if not found
     */
    public function getContext(string $name): mixed
    {
        return match ($name) {
            'sessionService' => $this->sessionService,
            'logger' => $this->logger,
            'config' => $this->config,
            'sessionPayloadFactory' => $this->sessionPayloadFactory,
            'jsonAdapter' => $this->jsonAdapter,
            'clientDetector' => $this->clientDetector,
            default => null,
        };
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
    ): ResponseInterface {
        // Try to find an existing session by cookie or header
        $sessionId = $this->extractSessionId($request);
        $session = null;
        $hadSessionIdButInvalid = false;

        if ($sessionId !== null) {
            $session = $this->sessionService->validateSession($sessionId);
            // Mark that the client sent a session ID, but it's not valid
            if ($session === null) {
                $hadSessionIdButInvalid = true;
            }
        }

        // Create SessionPayload for this request in any case - we'll need it
        $sessionPayload = $this->sessionPayloadFactory->createFromRequest($request);
        $payload = $this->jsonAdapter->serialize($sessionPayload);

        // Determine if the client is a browser
        $isBrowser = $sessionPayload->isBrowser();

        // If session was not found by cookie/header
        if ($session === null) {
            // If client sent a session but it's invalid, or it's a browser with browserNewSession enabled
            // always create a new session, bypassing fingerprint search
            if (($isBrowser && $this->config->browserNewSession) || $hadSessionIdButInvalid) {
                $session = $this->sessionService->createSession(
                    userId: null,
                    payload: $payload,
                    ttl: $this->config->sessionTtl,
                );

                $logContext = [
                    'session_id' => $session->id,
                    'user_agent' => $sessionPayload->userAgent,
                ];

                if ($hadSessionIdButInvalid) {
                    $this->logger->info('Invalid session ID detected, created new session', [
                        ...$logContext,
                        'invalid_session_id' => $sessionId,
                    ]);
                } else {
                    $this->logger->debug('Created new browser session', $logContext);
                }
            }
            // For non-browsers or when browserNewSession is disabled
            // try to use fingerprint if enabled
            elseif ($this->config->useFingerprint) {
                // Create a temporary session for the request so ClientDetector can work
                $tempSession = $this->sessionService->createSession(
                    userId: null,
                    payload: $payload,
                    ttl: 60, // Short lifetime as this is a temporary session
                );

                // Add temporary session to request attributes
                $requestWithTempSession = $request->withAttribute('session', $tempSession);

                // Search for similar clients using ClientDetector
                $similarClients = $this->clientDetector->findSimilarClients($requestWithTempSession);

                // If similar clients are found, use the session of the first client
                // (since they are sorted by similarity level)
                if (!empty($similarClients)) {
                    $bestMatch = $similarClients[0];
                    $matchedSession = $this->sessionService->validateSession($bestMatch->id);

                    if ($matchedSession !== null) {
                        $session = $matchedSession;
                        $this->logger->debug('Restored session by fingerprint', [
                            'session_id' => $session->id,
                            'client_ip' => $sessionPayload->ip,
                            'user_agent' => $sessionPayload->userAgent,
                        ]);

                        // Delete temporary session since we found an existing one
                        $this->sessionService->deleteSession($tempSession->id);
                    } else {
                        // If the best match session is not valid (expired or deleted),
                        // use the temporary session
                        $session = $tempSession;
                    }
                } else {
                    // If no similar clients found, use the temporary session
                    $session = $tempSession;
                    $this->logger->debug('Created new session, no similar clients found', [
                        'session_id' => $session->id,
                    ]);
                }
            } else {
                // If session not found and fingerprinting is disabled, simply create a new one
                $session = $this->sessionService->createSession(
                    userId: null,
                    payload: $payload,
                    ttl: $this->config->sessionTtl,
                );

                $this->logger->debug('Created new session', ['session_id' => $session->id]);
            }
        }

        // Add session to request attributes
        $request = $request->withAttribute('session', $session);

        // Process the request
        $response = $handler->handle($request);

        // Update session on successful request
        if ($response->getStatusCode() < 400) {
            $this->sessionService->refreshSession($session->id, $this->config->sessionTtl);

            // Set session cookie
            $response = $this->addSessionCookie($response, $session);
        }

        return $response;
    }

    private function extractSessionId(ServerRequestInterface $request): ?string
    {
        // Extract from header
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Extract from cookie
        $cookies = $request->getCookieParams();
        if (!isset($cookies[$this->config->cookieName])) {
            return null;
        }

        $cookie = $cookies[$this->config->cookieName];

        return \is_string($cookie) ? $cookie : null;
    }

    private function addSessionCookie(ResponseInterface $response, Session $session): ResponseInterface
    {
        $expires = gmdate('D, d M Y H:i:s T', $session->expiresAt);

        return $response->withAddedHeader(
            'Set-Cookie',
            \sprintf(
                '%s=%s; Expires=%s; Path=/; HttpOnly; SameSite=Lax',
                $this->config->cookieName,
                $session->id,
                $expires,
            ),
        );
    }
}
