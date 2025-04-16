<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Middleware;

use App\Application\Middleware\SessionMiddleware;
use App\Domain\Session\Session;
use App\Domain\Session\SessionConfig;
use App\Domain\Session\SessionRepository;
use App\Domain\Session\SessionService;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class SessionMiddlewareTest extends TestCase
{
    private TestSessionRepository $repository;

    private SessionService $sessionService;

    private TestLogger $logger;

    private SessionMiddleware $middleware;

    private TestRequestHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new TestSessionRepository();
        $this->sessionService = new SessionService($this->repository);
        $this->logger = new TestLogger();

        $config = SessionConfig::fromArray([
            'cookie_name' => 'session',
            'cookie_ttl' => 86400,
            'session_ttl' => 3600,
            'use_fingerprint' => false,
        ]);

        $this->middleware = new SessionMiddleware($this->sessionService, $this->logger, $config);
        $this->handler = new TestRequestHandler();
    }

    public function testCreatesNewSessionWhenNoSessionIdInRequest(): void
    {
        $request = new ServerRequest('GET', '/');
        $this->handler->response = new Response();

        $response = $this->middleware->process($request, $this->handler);

        // Проверяем, что была создана новая сессия
        self::assertCount(1, $this->repository->sessions);

        // Проверяем, что в атрибутах запроса есть сессия
        $processedRequest = $this->handler->lastRequest;
        self::assertNotNull($processedRequest);

        $session = $processedRequest->getAttribute('session');
        self::assertInstanceOf(Session::class, $session);
        self::assertNull($session->userId);

        // Проверяем логирование
        self::assertCount(1, $this->logger->logs);
        self::assertStringContainsString('Created new session', $this->logger->logs[0]['message']);

        // Проверяем cookie в ответе
        self::assertTrue($response->hasHeader('Set-Cookie'));
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('session=', $cookie);
        self::assertStringContainsString('HttpOnly', $cookie);
    }

    public function testUsesExistingSessionFromCookie(): void
    {
        // Создаем сессию в репозитории
        $existingSession = new Session(
            id: 'existing-session-id',
            userId: 1,
            payload: '{}',
            expiresAt: time() + 3600,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $this->repository->sessions['existing-session-id'] = $existingSession;

        // Создаем запрос с cookie
        $request = new ServerRequest('GET', '/');
        $request = $request->withCookieParams(['session' => 'existing-session-id']);

        $this->handler->response = new Response();

        $response = $this->middleware->process($request, $this->handler);

        // Проверяем, что существующая сессия получена и не создана новая
        self::assertCount(1, $this->repository->sessions);

        // Проверяем атрибуты запроса
        $processedRequest = $this->handler->lastRequest;
        self::assertNotNull($processedRequest);

        $session = $processedRequest->getAttribute('session');
        self::assertSame($existingSession, $session);

        // Проверяем cookie в ответе
        self::assertTrue($response->hasHeader('Set-Cookie'));
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('session=existing-session-id', $cookie);
    }

    public function testUsesExistingSessionFromBearerToken(): void
    {
        // Создаем сессию в репозитории
        $existingSession = new Session(
            id: 'token-session-id',
            userId: 1,
            payload: '{}',
            expiresAt: time() + 3600,
            createdAt: time() - 100,
            updatedAt: time() - 50,
        );
        $this->repository->sessions['token-session-id'] = $existingSession;

        // Создаем запрос с заголовком Authorization
        $request = new ServerRequest('GET', '/');
        $request = $request->withHeader('Authorization', 'Bearer token-session-id');

        $this->handler->response = new Response();

        $response = $this->middleware->process($request, $this->handler);

        // Проверяем, что существующая сессия получена и не создана новая
        self::assertCount(1, $this->repository->sessions);

        // Проверяем атрибуты запроса
        $processedRequest = $this->handler->lastRequest;
        self::assertNotNull($processedRequest);

        $session = $processedRequest->getAttribute('session');
        self::assertSame($existingSession, $session);

        // Проверяем cookie в ответе
        self::assertTrue($response->hasHeader('Set-Cookie'));
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('session=token-session-id', $cookie);
    }

    public function testCreatesNewSessionWhenExistingSessionIsInvalid(): void
    {
        // Создаем просроченную сессию в репозитории
        $expiredSession = new Session(
            id: 'invalid-session-id',
            userId: 1,
            payload: '{}',
            expiresAt: time() - 100, // В прошлом
            createdAt: time() - 200,
            updatedAt: time() - 100,
        );
        $this->repository->sessions['invalid-session-id'] = $expiredSession;

        // Создаем запрос с cookie
        $request = new ServerRequest('GET', '/');
        $request = $request->withCookieParams(['session' => 'invalid-session-id']);

        $this->handler->response = new Response();

        $response = $this->middleware->process($request, $this->handler);

        // Проверяем, что была создана новая сессия
        self::assertCount(2, $this->repository->sessions); // Просроченная + новая

        // Проверяем атрибуты запроса
        $processedRequest = $this->handler->lastRequest;
        self::assertNotNull($processedRequest);

        $session = $processedRequest->getAttribute('session');
        self::assertInstanceOf(Session::class, $session);
        self::assertNotSame($expiredSession, $session);
        self::assertNull($session->userId);

        // Проверяем cookie в ответе
        self::assertTrue($response->hasHeader('Set-Cookie'));
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertStringContainsString('session=', $cookie);
        self::assertStringNotContainsString('session=invalid-session-id', $cookie);
    }

    public function testDoesNotRefreshSessionForErrorResponses(): void
    {
        $request = new ServerRequest('GET', '/');
        $this->handler->response = new Response(500);

        $response = $this->middleware->process($request, $this->handler);

        // Проверяем, что была создана сессия
        self::assertCount(1, $this->repository->sessions);

        // Проверяем, что в ответе нет cookie
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }
}

final class TestRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $lastRequest = null;

    public ResponseInterface $response;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return $this->response;
    }
}

final class TestLogger implements LoggerInterface
{
    /** @var array<array{level: string, message: string, context: array<mixed>}> */
    public array $logs = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $levelString = match (true) {
            \is_string($level) => $level,
            \is_scalar($level) => (string) $level,
            default => 'unknown',
        };

        $this->logs[] = [
            'level' => $levelString,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
}

/**
 * Тестовый репозиторий для тестирования SessionMiddleware.
 */
final class TestSessionRepository implements SessionRepository
{
    /** @var array<string, Session> */
    public array $sessions = [];

    public function findById(string $id): ?Session
    {
        return $this->sessions[$id] ?? null;
    }

    /**
     * @return array<Session>
     */
    public function findByUserId(int $userId): array
    {
        return array_filter(
            $this->sessions,
            static fn(Session $session) => $session->userId === $userId,
        );
    }

    public function save(Session $session): void
    {
        $this->sessions[$session->id] = $session;
    }

    public function delete(string $id): void
    {
        unset($this->sessions[$id]);
    }

    public function deleteExpired(): void
    {
        $now = time();

        foreach ($this->sessions as $id => $session) {
            if ($session->expiresAt < $now) {
                unset($this->sessions[$id]);
            }
        }
    }
}
