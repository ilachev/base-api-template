<?php

declare(strict_types=1);

namespace App\Application\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface Middleware
{
    public function process(
        ServerRequestInterface $request,
        RequestHandler $handler,
    ): ResponseInterface;
}
