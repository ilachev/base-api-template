<?php

declare(strict_types=1);

namespace Tests\Integration\Stats;

use App\Application\Client\ClientDataFactory;
use App\Domain\Session\SessionService;
use App\Infrastructure\Hydrator\JsonFieldAdapter;
use App\Infrastructure\Storage\StorageInterface;
use Tests\Integration\IntegrationTestCase;

final class ApiStatsIntegrationTest extends IntegrationTestCase
{
    private SessionService $sessionService;

    private StorageInterface $storage;

    private ClientDataFactory $clientDataFactory;

    private JsonFieldAdapter $jsonAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SessionService $sessionService */
        $sessionService = $this->container->get(SessionService::class);
        $this->sessionService = $sessionService;

        /** @var StorageInterface $storage */
        $storage = $this->container->get(StorageInterface::class);
        $this->storage = $storage;

        /** @var ClientDataFactory $clientDataFactory */
        $clientDataFactory = $this->container->get(ClientDataFactory::class);
        $this->clientDataFactory = $clientDataFactory;

        /** @var JsonFieldAdapter $jsonAdapter */
        $jsonAdapter = $this->container->get(JsonFieldAdapter::class);
        $this->jsonAdapter = $jsonAdapter;

        // Очищаем таблицу перед каждым тестом
        $this->storage->execute('DELETE FROM api_stats');
    }

    public function testApiRequestsAreHandled(): void
    {
        // Делаем тестовый запрос
        $response = $this->makeRequest('GET', '/');

        // Проверяем статус ответа (404 ожидаем, так как в тестовой среде у нас нет обработчика для /)
        self::assertSame(404, $response->getStatusCode());
    }

    public function testNonExistentPathRequestReturns404(): void
    {
        // Делаем запрос на несуществующий путь
        $response = $this->makeRequest('GET', '/non-existent-path');

        // Проверяем статус ответа
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * Проверяет, что при обращении к API создается запись в api_stats.
     */
    public function testApiStatsRecordingForHomeEndpoint(): void
    {
        // Создаем сессию перед запросом, чтобы был client_id
        $request = $this->createRequest(
            'GET',
            '/api/v1/home',
            ['User-Agent' => 'PHPUnit Test Browser'],
        );

        $clientData = $this->clientDataFactory->createFromRequest($request);
        $payload = $this->jsonAdapter->serialize($clientData);

        $session = $this->sessionService->createSession(
            userId: null,
            payload: $payload,
        );

        $sessionId = $session->id;

        // Выполняем запрос с сессией
        $request = $this->createRequest(
            'GET',
            '/api/v1/home',
            ['User-Agent' => 'PHPUnit Test Browser'],
        )->withAttribute('session', $session);

        // Создаем фейковую cookie с сессией
        $cookies = ['session' => $sessionId];
        $response = $this->makeRequest(
            'GET',
            '/api/v1/home',
            ['User-Agent' => 'PHPUnit Test Browser'],
            null,
            $cookies,
        );

        // Проверяем, что получили успешный ответ
        self::assertEquals(200, $response->getStatusCode(), 'Запрос должен быть успешным');

        // Проверяем, что в таблице api_stats появилась запись
        $stats = $this->storage->query('SELECT * FROM api_stats WHERE session_id = :session_id', [
            'session_id' => $sessionId,
        ]);

        self::assertNotEmpty($stats, 'В таблице api_stats должна появиться запись о запросе');

        // Проверяем данные записи
        $record = $stats[0];
        self::assertEquals('/api/v1/home', $record['route'], 'Маршрут должен соответствовать запросу');
        self::assertEquals('GET', $record['method'], 'HTTP метод должен соответствовать запросу');
        self::assertEquals(200, $record['status_code'], 'Статус код должен быть 200');
        self::assertNotNull($record['execution_time'], 'Время выполнения должно быть записано');
        self::assertNotNull($record['request_time'], 'Время запроса должно быть записано');
    }

    /**
     * Проверяет запись статистики для несуществующего маршрута (404).
     */
    public function testApiStatsRecordingForNonExistentRoute(): void
    {
        // Создаем сессию
        $request = $this->createRequest(
            'GET',
            '/api/non-existent-route',
            ['User-Agent' => 'PHPUnit Test Browser'],
        );

        $clientData = $this->clientDataFactory->createFromRequest($request);
        $payload = $this->jsonAdapter->serialize($clientData);

        $session = $this->sessionService->createSession(
            userId: null,
            payload: $payload,
        );

        $sessionId = $session->id;
        $cookies = ['session' => $sessionId];

        // Делаем запрос к несуществующему маршруту
        $response = $this->makeRequest(
            'GET',
            '/api/non-existent-route',
            ['User-Agent' => 'PHPUnit Test Browser'],
            null,
            $cookies,
        );

        // Проверяем, что получили 404
        self::assertEquals(404, $response->getStatusCode(), 'Запрос должен вернуть 404');

        // Проверяем статистику
        $stats = $this->storage->query('SELECT * FROM api_stats WHERE session_id = :session_id AND route = :route', [
            'session_id' => $sessionId,
            'route' => '/api/non-existent-route',
        ]);

        self::assertNotEmpty($stats, 'Должна быть запись о запросе к несуществующему маршруту');
        self::assertEquals('GET', $stats[0]['method'], 'HTTP метод должен быть GET');
        self::assertEquals(404, $stats[0]['status_code'], 'Статус код должен быть 404');
    }
}
