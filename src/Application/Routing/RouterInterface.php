<?php

declare(strict_types=1);

namespace App\Application\Routing;

use Psr\Http\Message\ServerRequestInterface;

interface RouterInterface
{
    public function dispatch(ServerRequestInterface $request): RouteResult;
}
