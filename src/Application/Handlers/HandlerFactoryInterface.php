<?php

declare(strict_types=1);

namespace App\Application\Handlers;

interface HandlerFactoryInterface
{
    /**
     * @param class-string<HandlerInterface> $handlerClass
     */
    public function create(string $handlerClass): HandlerInterface;
}
