<?php

declare(strict_types=1);

use App\Infrastructure\Console\MigrateCommand;
use App\Infrastructure\DI\Container;
use App\Infrastructure\Storage\Migration\MigrationService;
use App\Infrastructure\Storage\StorageInterface;

require_once __DIR__ . '/../../vendor/autoload.php';

// Log environment for debugging
echo "Environment variables for database connection:\n";
echo 'DB_HOST: ' . (getenv('DB_HOST') ?: 'not set') . "\n";
echo 'DB_PORT: ' . (getenv('DB_PORT') ?: 'not set') . "\n";
echo 'DB_NAME: ' . (getenv('DB_NAME') ?: 'not set') . "\n";
echo 'DB_USER: ' . (getenv('DB_USER') ?: 'not set') . "\n";
echo 'GitHub Actions: ' . (getenv('GITHUB_ACTIONS') ?: 'not set') . "\n\n";

try {
    // Для тестов используем существующую БД приложения,
    // но сначала удаляем все таблицы, а затем накатываем миграции заново
    /** @var callable(Container<object>): void $containerConfig */
    $containerConfig = require __DIR__ . '/../../config/container.php';
    $container = new Container();
    $containerConfig($container);

    // Получаем PostgreSQL хранилище
    /** @var StorageInterface $storage */
    $storage = $container->get(StorageInterface::class);

    // Удаляем все существующие таблицы
    try {
        $tables = $storage->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        foreach ($tables as $table) {
            $storage->execute("DROP TABLE IF EXISTS \"{$table['tablename']}\" CASCADE");
        }
    } catch (Exception $e) {
        echo 'Warning during table cleanup: ' . $e->getMessage() . "\n";
        echo "Continuing with migrations...\n";
    }

    // Накатываем миграции заново
    $migrationService = $container->get(MigrationService::class);
    $command = new MigrateCommand($migrationService);
    $command->migrate();

    // Директория для временных файлов
    $varDir = __DIR__ . '/../../var';
    if (!is_dir($varDir)) {
        mkdir($varDir, 0o755, true);
    }
} catch (Exception $e) {
    echo 'Error in bootstrap: ' . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
