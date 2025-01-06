<?php

declare(strict_types=1);

namespace App\Infrastructure\DI;

use Psr\Container\ContainerExceptionInterface;

final class ContainerException extends \Exception implements ContainerExceptionInterface {}
