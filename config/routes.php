<?php

declare(strict_types=1);

use App\Application\Handlers\HandlerInterface;
use App\Application\Handlers\HomeHandler;

/**
 * @return array<array{
 *     method: string,
 *     path: string,
 *     handler: class-string<HandlerInterface>
 * }>
 */
return [
    [
        'method' => 'GET',
        'path' => '/home',
        'handler' => HomeHandler::class,
    ],
];
