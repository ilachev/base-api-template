<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Home;

use App\Domain\Home\HomeService;
use PHPUnit\Framework\TestCase;

final class HomeServiceTest extends TestCase
{
    public function testGetWelcomeMessage(): void
    {
        $service = new HomeService();
        self::assertEquals('Welcome to our API', $service->getWelcomeMessage());
    }
}
