<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Http\JsonResponse;
use App\Application\Mappers\HomeMapper;
use App\Domain\Home\HomeService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HomeHandler extends AbstractJsonHandler
{
    public function __construct(
        private HomeService $homeService,
        private HomeMapper $homeMapper,
        JsonResponse $jsonResponse,
    ) {
        parent::__construct($jsonResponse);
    }

    /**
     * @throws \JsonException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Get message from domain service
        $message = $this->homeService->getWelcomeMessage();

        // Map domain data to API response
        $response = $this->homeMapper->toResponse($message);

        return $this->jsonResponse($response->serializeToJsonString());
    }
}
