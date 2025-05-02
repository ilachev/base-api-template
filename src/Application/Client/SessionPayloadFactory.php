<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Session\SessionPayload;
use Psr\Http\Message\ServerRequestInterface;

interface SessionPayloadFactory
{
    public function createFromRequest(ServerRequestInterface $request): SessionPayload;

    public function createDefault(): SessionPayload;
}
