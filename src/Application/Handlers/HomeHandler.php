<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Api\V1\HomeData;
use App\Api\V1\HomeResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HomeHandler extends AbstractJsonHandler
{
    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $data = new HomeData();
        $data->setMessage('Welcome to our API');

        $response = new HomeResponse();
        $response->setData($data);

        return $this->jsonResponse($response->serializeToJsonString());
    }
}
