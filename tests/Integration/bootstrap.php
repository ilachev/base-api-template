<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Настройка тестовой БД и других ресурсов
$testDbPath = __DIR__ . '/../../var/test.sqlite';

// Удаляем тестовую БД, если она существует
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

// Директория для временных файлов
$varDir = __DIR__ . '/../../var';
if (!is_dir($varDir)) {
    mkdir($varDir, 0o755, true);
}
