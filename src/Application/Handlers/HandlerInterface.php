<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface HandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
