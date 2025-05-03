<?php

declare(strict_types=1);

use App\Infrastructure\App;
use App\Infrastructure\Console\MigrateCommand;
use App\Infrastructure\Storage\Migration\MigrationService;
use App\Infrastructure\Storage\StorageInterface;
use Tests\Integration\TestAppFactory;

require_once __DIR__ . '/../../vendor/autoload.php';

echo "Environment variables for database connection:\n";
echo 'DB_HOST: ' . (getenv('DB_HOST') ?: 'not set') . "\n";
echo 'DB_PORT: ' . (getenv('DB_PORT') ?: 'not set') . "\n";
echo 'DB_NAME: ' . (getenv('DB_NAME') ?: 'not set') . "\n";
echo 'DB_USER: ' . (getenv('DB_USER') ?: 'not set') . "\n";
echo 'GitHub Actions: ' . (getenv('GITHUB_ACTIONS') ?: 'not set') . "\n\n";

try {
    // Create a single application instance that will be reused across all tests
    $app = new App(__DIR__ . '/../../config/container.php');
    TestAppFactory::setApp($app);

    // Get services from the container
    $container = $app->getContainer();

    /** @var StorageInterface $storage */
    $storage = $container->get(StorageInterface::class);

    try {
        $tables = $storage->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        foreach ($tables as $table) {
            $storage->execute("DROP TABLE IF EXISTS \"{$table['tablename']}\" CASCADE");
        }
    } catch (Exception $e) {
        echo 'Warning during table cleanup: ' . $e->getMessage() . "\n";
        echo "Continuing with migrations...\n";
    }

    $migrationService = $container->get(MigrationService::class);
    $command = new MigrateCommand($migrationService);
    $command->migrate();

    $varDir = __DIR__ . '/../../var';
    if (!is_dir($varDir)) {
        mkdir($varDir, 0o755, true);
    }
} catch (Exception $e) {
    echo 'Error in bootstrap: ' . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
