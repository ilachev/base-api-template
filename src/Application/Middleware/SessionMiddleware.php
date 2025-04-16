<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Client\ClientDataFactory;
use App\Domain\Session\Session;
use App\Domain\Session\SessionConfig;
use App\Domain\Session\SessionService;
use App\Infrastructure\Hydrator\JsonFieldAdapter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionService $sessionService,
        private LoggerInterface $logger,
        private SessionConfig $config,
        private ClientDataFactory $clientDataFactory,
        private JsonFieldAdapter $jsonAdapter,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Пытаемся найти существующую сессию
        $sessionId = $this->extractSessionId($request);
        $session = null;

        if ($sessionId !== null) {
            $session = $this->sessionService->validateSession($sessionId);
        }

        // Если сессия не найдена или истекла, создаем новую
        if ($session === null) {
            // Создаем ClientData и сериализуем его через JsonFieldAdapter
            $clientData = $this->clientDataFactory->createFromRequest($request);
            $payload = $this->jsonAdapter->serialize($clientData);

            $session = $this->sessionService->createSession(
                userId: null,
                payload: $payload,
                ttl: $this->config->cookieTtl,
            );

            $this->logger->debug('Created new session', ['session_id' => $session->id]);
        }

        // Добавляем сессию в атрибуты запроса
        $request = $request->withAttribute('session', $session);

        // Обрабатываем запрос
        $response = $handler->handle($request);

        // Обновляем сессию при успешном запросе
        if ($response->getStatusCode() < 400) {
            $this->sessionService->refreshSession($session->id, $this->config->sessionTtl);

            // Устанавливаем cookie с сессией
            $response = $this->addSessionCookie($response, $session);
        }

        return $response;
    }

    private function extractSessionId(ServerRequestInterface $request): ?string
    {
        // Извлекаем из заголовка
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Извлекаем из cookie
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
