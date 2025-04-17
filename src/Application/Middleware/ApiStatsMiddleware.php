<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Routing\RouteResult;
use App\Domain\Session\Session;
use App\Domain\Session\SessionService;
use App\Domain\Stats\ApiStat;
use App\Domain\Stats\ApiStatService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class ApiStatsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ApiStatService $statsService,
        private SessionService $sessionService,
        private LoggerInterface $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $startTime = hrtime(true);

        // Получаем сессию из запроса (добавляется в SessionMiddleware)
        /** @var Session|null $session */
        $session = $request->getAttribute('session');
        $sessionId = $session?->id;

        // Для тестов разрешаем также использовать sessionId напрямую, если сессия не найдена
        if ($sessionId === null && $request->getAttribute('sessionId') !== null) {
            $sessionIdAttr = $request->getAttribute('sessionId');
            $sessionId = \is_string($sessionIdAttr) ? $sessionIdAttr : null;
        }

        // Обработка запроса
        $response = $handler->handle($request);

        // Вычисление времени выполнения запроса
        $executionTime = (hrtime(true) - $startTime) / 1_000_000;

        // Получаем информацию о маршруте
        /** @var RouteResult|null $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $route = $request->getUri()->getPath(); // По умолчанию используем путь из URI

        // Если найден маршрут и он валидный, используем его handler
        if ($routeResult !== null && $routeResult->isFound()) {
            $route = $routeResult->getHandler();
        }

        // Проверяем наличие сессии
        if ($sessionId === null) {
            $this->logger->debug('ApiStatsMiddleware: Skipping stats - no sessionId');

            return $response;
        }

        // В тестовом режиме (с атрибутом sessionId) пропускаем проверку существования сессии
        $isTestMode = $request->getAttribute('sessionId') !== null;

        // Если не тестовый режим, проверяем существование сессии
        if (!$isTestMode) {
            // Проверяем, существует ли сессия в БД с помощью SessionService
            $validSession = $this->sessionService->validateSession($sessionId);

            if ($validSession === null) {
                $this->logger->debug('ApiStatsMiddleware: Skipping stats - session does not exist', [
                    'session_id' => $sessionId,
                ]);

                return $response;
            }
        }

        $this->logger->debug('ApiStatsMiddleware: Saving API stats', [
            'session_id' => $sessionId,
            'route' => $route,
            'method' => $request->getMethod(),
            'test_mode' => $isTestMode,
        ]);

        // Создаем объект статистики API вызова
        $stat = new ApiStat(
            id: null,
            sessionId: $sessionId,
            route: $route,
            method: $request->getMethod(),
            statusCode: $response->getStatusCode(),
            executionTime: $executionTime,
            requestTime: time(),
        );

        // Сохраняем статистику напрямую
        // В реальном высоконагруженном проекте здесь можно использовать очередь задач
        $this->statsService->saveApiCall($stat);

        return $response;
    }
}
