<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Client\GeoLocationConfig;
use App\Infrastructure\Logger\Logger;

/**
 * Команда для обновления базы данных геолокации IP2Location.
 */
final readonly class UpdateGeoIPCommand
{
    public function __construct(
        private GeoLocationConfig $config,
        private Logger $logger,
    ) {}

    /**
     * Выполняет обновление базы данных геолокации.
     */
    public function execute(): void
    {
        $this->logger->info('Starting GeoIP database update');

        // Проверяем наличие токена
        if (empty($this->config->downloadToken)) {
            $this->logger->error('IP2Location download token is not set');

            return;
        }

        // Создаем директорию для базы данных, если она не существует
        $dbDir = \dirname($this->config->dbPath);
        if (!is_dir($dbDir) && !mkdir($dbDir, 0o755, true)) {
            $this->logger->error('Failed to create directory for GeoIP database', [
                'directory' => $dbDir,
            ]);

            return;
        }

        // Формируем URL для скачивания
        $downloadUrl = $this->config->getDownloadUrl();
        $this->logger->info('Downloading GeoIP database', [
            'url' => $downloadUrl,
            'database_code' => $this->config->databaseCode,
        ]);

        // Временный файл для скачивания
        $tempFile = $this->config->dbPath . '.tmp';
        $zipFile = $this->config->dbPath . '.zip';

        try {
            // Скачиваем файл
            $this->downloadFile($downloadUrl, $zipFile);
            $this->logger->info('Downloaded GeoIP database archive', [
                'size' => filesize($zipFile),
            ]);

            // Распаковываем архив
            $this->extractDatabase($zipFile, $dbDir, $tempFile);

            // Проверяем, что файл существует и имеет ненулевой размер
            if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                throw new \RuntimeException('Extracted database file is empty or does not exist');
            }

            // Переименовываем временный файл в целевой
            if (file_exists($this->config->dbPath)) {
                unlink($this->config->dbPath);
            }
            rename($tempFile, $this->config->dbPath);

            $this->logger->info('GeoIP database updated successfully', [
                'path' => $this->config->dbPath,
                'size' => filesize($this->config->dbPath),
            ]);

            // Удаляем временные файлы
            if (file_exists($zipFile)) {
                unlink($zipFile);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update GeoIP database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Удаляем временные файлы в случае ошибки
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            if (file_exists($zipFile)) {
                unlink($zipFile);
            }
        }
    }

    /**
     * Скачивает файл по URL.
     *
     * @param string $url URL для скачивания
     * @param string $destination Путь для сохранения файла
     * @throws \RuntimeException Если не удалось скачать файл
     */
    private function downloadFile(string $url, string $destination): void
    {
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: PHP/' . PHP_VERSION,
                ],
                'timeout' => 120, // 2 минуты
                'follow_location' => 1,
                'max_redirects' => 3,
            ],
        ];

        $context = stream_context_create($options);
        $content = file_get_contents($url, false, $context);

        if ($content === false) {
            throw new \RuntimeException('Failed to download file from ' . $url);
        }

        if (file_put_contents($destination, $content) === false) {
            throw new \RuntimeException('Failed to save downloaded file to ' . $destination);
        }
    }

    /**
     * Извлекает базу данных из ZIP-архива.
     *
     * @param string $zipFile Путь к ZIP-архиву
     * @param string $extractDir Директория для распаковки
     * @param string $targetFile Путь для сохранения извлеченного файла
     * @throws \RuntimeException Если не удалось распаковать архив
     */
    private function extractDatabase(string $zipFile, string $extractDir, string $targetFile): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new \RuntimeException('Failed to open ZIP archive: ' . $zipFile);
        }

        // Создаем временную директорию для распаковки
        $tempDir = $extractDir . '/temp_' . uniqid();
        if (!is_dir($tempDir) && !mkdir($tempDir, 0o755, true)) {
            $zip->close();

            throw new \RuntimeException('Failed to create temporary directory for extraction');
        }

        // Распаковываем архив
        if (!$zip->extractTo($tempDir)) {
            $zip->close();
            $this->removeDirectory($tempDir);

            throw new \RuntimeException('Failed to extract ZIP archive');
        }
        $zip->close();

        // Ищем BIN-файл в распакованной директории
        $binFiles = glob($tempDir . '/*.BIN');
        if (empty($binFiles)) {
            $this->removeDirectory($tempDir);

            throw new \RuntimeException('No BIN files found in the extracted archive');
        }

        // Копируем первый найденный BIN-файл в целевой файл
        if (!copy($binFiles[0], $targetFile)) {
            $this->removeDirectory($tempDir);

            throw new \RuntimeException('Failed to copy extracted BIN file to target location');
        }

        // Удаляем временную директорию
        $this->removeDirectory($tempDir);
    }

    /**
     * Рекурсивно удаляет директорию и все ее содержимое.
     *
     * @param string $dir Путь к директории
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $scanned = scandir($dir);
        if ($scanned !== false) {
            $files = array_diff($scanned, ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . (string) $file;
                is_dir($path) ? $this->removeDirectory($path) : unlink($path);
            }
        }
        rmdir($dir);
    }
}
