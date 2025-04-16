<?php

declare(strict_types=1);

namespace App\Domain\Home;

final readonly class HomeService
{
    public function getWelcomeMessage(): string
    {
        return 'Welcome to our API';
    }
}
