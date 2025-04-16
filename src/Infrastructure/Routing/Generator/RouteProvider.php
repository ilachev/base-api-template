<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing\Generator;

interface RouteProvider
{
    /**
     * @return array<array{
     *     method: string,
     *     path: string,
     *     handler: string,
     *     operation_id?: string
     * }>
     */
    public function getRoutes(): array;
}
