<?php

declare(strict_types=1);

namespace App\Application\Client;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Интерфейс для определения и сопоставления клиентов на основе fingerprint.
 */
interface ClientDetectorInterface
{
    /**
     * Определяет, принадлежит ли запрос уже известному клиенту.
     *
     * @param ServerRequestInterface $request Текущий HTTP-запрос
     * @param bool $includeCurrent Включать ли текущую сессию в результаты поиска (для тестов)
     * @return array<ClientIdentity> Список найденных похожих клиентов, отсортированный по схожести
     */
    public function findSimilarClients(ServerRequestInterface $request, bool $includeCurrent = false): array;

    /**
     * Проверяет, является ли текущий запрос потенциально опасным
     * (например, слишком много сессий с одного IP).
     *
     * @param ServerRequestInterface $request Текущий HTTP-запрос
     */
    public function isRequestSuspicious(ServerRequestInterface $request): bool;
}
