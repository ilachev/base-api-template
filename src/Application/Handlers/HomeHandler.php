<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class HomeHandler extends AbstractJsonHandler
{
    /**
     * @throws JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->jsonResponse(['message' => 'Welcome to our API']);
    }
}
