<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Client\ClientDataFactory;
use App\Application\Client\ClientDetectorInterface;
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
        private ClientDetectorInterface $clientDetector,
    ) {}

    /**
     * Получает зависимость по имени для тестирования.
     *
     * @param string $name Имя зависимости
     * @return mixed Значение зависимости или null, если не найдена
     */
    public function getContext(string $name): mixed
    {
        return match ($name) {
            'sessionService' => $this->sessionService,
            'logger' => $this->logger,
            'config' => $this->config,
            'clientDataFactory' => $this->clientDataFactory,
            'jsonAdapter' => $this->jsonAdapter,
            'clientDetector' => $this->clientDetector,
            default => null,
        };
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Пытаемся найти существующую сессию по cookie или заголовку
        $sessionId = $this->extractSessionId($request);
        $session = null;
        $hadSessionIdButInvalid = false;

        if ($sessionId !== null) {
            $session = $this->sessionService->validateSession($sessionId);
            // Отмечаем, что клиент прислал ID сессии, но она не валидна
            if ($session === null) {
                $hadSessionIdButInvalid = true;
            }
        }

        // Создаем ClientData для этого запроса в любом случае - он понадобится
        $clientData = $this->clientDataFactory->createFromRequest($request);
        $payload = $this->jsonAdapter->serialize($clientData);

        // Определяем, является ли клиент браузером
        $isBrowser = $clientData->isBrowser();

        // Если сессия не найдена по cookie/заголовку
        if ($session === null) {
            // Если клиент прислал сессию, но она не валидна, или это браузер с включенной настройкой
            // browserNewSession - всегда создаем новую сессию, минуя поиск по отпечатку
            if (($isBrowser && $this->config->browserNewSession) || $hadSessionIdButInvalid) {
                $session = $this->sessionService->createSession(
                    userId: null,
                    payload: $payload,
                    ttl: $this->config->sessionTtl,
                );

                $logContext = [
                    'session_id' => $session->id,
                    'user_agent' => $clientData->userAgent,
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
            // Для не-браузеров или при выключенной настройке browserNewSession
            // пытаемся использовать отпечаток, если включено
            elseif ($this->config->useFingerprint) {
                // Создаем временную сессию для запроса, чтобы ClientDetector мог работать
                $tempSession = $this->sessionService->createSession(
                    userId: null,
                    payload: $payload,
                    ttl: 60, // Короткий срок жизни, т.к. это временная сессия
                );

                // Добавляем временную сессию в атрибуты запроса
                $requestWithTempSession = $request->withAttribute('session', $tempSession);

                // Ищем похожих клиентов с помощью ClientDetector
                $similarClients = $this->clientDetector->findSimilarClients($requestWithTempSession);

                // Если найдены похожие клиенты, используем сессию первого клиента
                // (т.к. они отсортированы по уровню схожести)
                if (!empty($similarClients)) {
                    $bestMatch = $similarClients[0];
                    $matchedSession = $this->sessionService->validateSession($bestMatch->id);

                    if ($matchedSession !== null) {
                        $session = $matchedSession;
                        $this->logger->debug('Restored session by fingerprint', [
                            'session_id' => $session->id,
                            'client_ip' => $clientData->ip,
                            'user_agent' => $clientData->userAgent,
                        ]);

                        // Удаляем временную сессию, так как нашли существующую
                        $this->sessionService->deleteSession($tempSession->id);
                    } else {
                        // Если сессия лучшего совпадения не валидна (истекла или удалена),
                        // будем использовать временную сессию
                        $session = $tempSession;
                    }
                } else {
                    // Если похожих клиентов не найдено, используем временную сессию
                    $session = $tempSession;
                    $this->logger->debug('Created new session, no similar clients found', [
                        'session_id' => $session->id,
                    ]);
                }
            } else {
                // Если сессия не найдена и fingerprinting отключен, просто создаем новую
                $session = $this->sessionService->createSession(
                    userId: null,
                    payload: $payload,
                    ttl: $this->config->sessionTtl,
                );

                $this->logger->debug('Created new session', ['session_id' => $session->id]);
            }
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
