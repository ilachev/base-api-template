<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @extends AbstractJsonHandler<array<string, string>>
 */
final readonly class HomeHandler extends AbstractJsonHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->jsonResponse(['message' => 'Welcome to our API']);
    }
}
