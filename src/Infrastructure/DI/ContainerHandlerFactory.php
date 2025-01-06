<?php

declare(strict_types=1);

namespace App\Infrastructure\DI;

use App\Application\Handlers\HandlerFactoryInterface;
use App\Application\Handlers\HandlerInterface;

final readonly class ContainerHandlerFactory implements HandlerFactoryInterface
{
    /**
     * @param Container<object> $container
     */
    public function __construct(
        private Container $container,
    ) {}

    /**
     * @throws ContainerException
     */
    public function create(string $handlerClass): HandlerInterface
    {
        return $this->container->get($handlerClass);
    }
}
