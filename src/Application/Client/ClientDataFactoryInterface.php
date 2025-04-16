<?php

declare(strict_types=1);

namespace App\Application\Client;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Интерфейс фабрики для создания ClientData из HTTP запроса.
 */
interface ClientDataFactoryInterface
{
    /**
     * Создает объект ClientData из HTTP запроса.
     */
    public function createFromRequest(ServerRequestInterface $request): ClientData;

    /**
     * Создает объект ClientData с минимальными данными.
     */
    public function createDefault(): ClientData;
}
