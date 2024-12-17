<?php

declare(strict_types=1);

namespace App\Application\Error;

enum ApiError: string
{
    case NOT_FOUND = 'Not found';
    case INTERNAL_ERROR = 'Internal Server Error';

    public function text(): string
    {
        return $this->value;
    }
}
