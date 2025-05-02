<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Domain\Session\Session;
use App\Domain\Session\SessionService;
use App\Infrastructure\Logger\Logger;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class AuthMiddleware implements MiddlewareInterface
{
    // Пути, требующие аутентифицированного пользователя
    private const array PROTECTED_PATHS = [
        '/api/v1/user/',
        '/api/v1/admin/',
    ];
    private const string COOKIE_NAME = 'session';
    private const int COOKIE_TTL = 86400; // 24 часа

    public function __construct(
        private SessionService $sessionService,
        private Logger $logger,
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

        // Если нет сессии, создаем анонимную
        if ($session === null) {
            // Но сначала проверяем, является ли путь защищенным
            $path = $request->getUri()->getPath();

            foreach (self::PROTECTED_PATHS as $protectedPath) {
                if (str_starts_with($path, $protectedPath)) {
                    $jsonBody = json_encode(['error' => 'Unauthorized access']);

                    return new Response(
                        401,
                        ['Content-Type' => 'application/json'],
                        $jsonBody !== false ? $jsonBody : '{"error":"JSON encoding failed"}',
                    );
                }
            }

            // Если путь не защищенный, создаем анонимную сессию
            $payload = json_encode(['ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown']);
            if ($payload === false) {
                $payload = '{}';
            }

            $session = $this->sessionService->createSession(
                userId: null,
                payload: $payload,
                ttl: self::COOKIE_TTL,
            );

            $this->logger->info('Created anonymous session', ['session_id' => $session->id]);
        }

        // Добавляем сессию в атрибуты запроса
        $request = $request->withAttribute('session', $session);

        if ($session->userId !== null) {
            $request = $request->withAttribute('userId', $session->userId);
            $request = $request->withAttribute('isAuthenticated', true);
        } else {
            $request = $request->withAttribute('isAuthenticated', false);
        }

        // Обрабатываем запрос
        $response = $handler->handle($request);

        // Обновляем сессию и устанавливаем cookie
        if ($response->getStatusCode() < 400) {
            $session = $this->sessionService->refreshSession($session->id);

            // Добавляем cookie с сессией
            if ($session !== null) {
                $response = $this->addSessionCookie($response, $session);
            }
        }

        return $response;
    }

    /**
     * Extract session ID from the request.
     */
    private function extractSessionId(ServerRequestInterface $request): ?string
    {
        // Пытаемся извлечь токен из заголовка Authorization
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Пытаемся извлечь токен из куки
        $cookies = $request->getCookieParams();

        $cookieValue = $cookies[self::COOKIE_NAME] ?? null;

        return \is_string($cookieValue) ? $cookieValue : null;
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
