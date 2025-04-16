<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Routing\RouteResult;
use App\Domain\Session\Session;
use App\Domain\Stats\ApiStat;
use App\Domain\Stats\ApiStatService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ApiStatsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ApiStatService $statsService,
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

        // Сохраняем статистику только для запросов с сессией
        // Если нет ID сессии, прекращаем обработку
        if ($sessionId === null) {
            return $response;
        }

        $stat = new ApiStat(
            id: null,
            clientId: $sessionId, // используем $sessionId вместо $clientId
            route: $route,
            method: $request->getMethod(),
            statusCode: $response->getStatusCode(),
            executionTime: $executionTime,
            requestTime: time(),
        );

        $this->statsService->saveApiCall($stat);

        return $response;
    }
}
