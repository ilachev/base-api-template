<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Domain\Session\Session;
use App\Domain\Session\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class SessionMiddleware implements MiddlewareInterface
{
    private const string COOKIE_NAME = 'session';
    private const int COOKIE_TTL = 86400; // 24 часа

    public function __construct(
        private SessionService $sessionService,
        private LoggerInterface $logger,
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
            $session = $this->sessionService->createSession(
                userId: null,
                payload: (string) json_encode(['ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown']),
                ttl: self::COOKIE_TTL,
            );

            $this->logger->debug('Created new session', ['session_id' => $session->id]);
        }

        // Добавляем сессию в атрибуты запроса
        $request = $request->withAttribute('session', $session);

        // Обрабатываем запрос
        $response = $handler->handle($request);

        // Обновляем сессию при успешном запросе
        if ($response->getStatusCode() < 400) {
            $this->sessionService->refreshSession($session->id);

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
        if (!isset($cookies[self::COOKIE_NAME])) {
            return null;
        }

        $cookie = $cookies[self::COOKIE_NAME];

        return \is_string($cookie) ? $cookie : null;
    }

    private function addSessionCookie(ResponseInterface $response, Session $session): ResponseInterface
    {
        $expires = gmdate('D, d M Y H:i:s T', $session->expiresAt);

        return $response->withAddedHeader(
            'Set-Cookie',
            \sprintf(
                '%s=%s; Expires=%s; Path=/; HttpOnly; SameSite=Lax',
                self::COOKIE_NAME,
                $session->id,
                $expires,
            ),
        );
    }
}
