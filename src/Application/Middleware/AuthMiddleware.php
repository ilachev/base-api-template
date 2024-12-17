<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $token = $request->getHeaderLine('Authorization');

        if (empty($token) || $token !== '$EcR3t') {
            return new Response(
                401,
                [],
                'Unauthorized'
            );
        }

        return $handler->handle($request);
    }
}
