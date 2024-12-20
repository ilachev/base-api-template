<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\App;

$app = new App(__DIR__ . '/../config/container.php');
$app->run();
