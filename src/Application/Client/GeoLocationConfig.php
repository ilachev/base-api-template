<?php

declare(strict_types=1);

namespace App\Application\Client;

/**
 * Конфигурация для сервиса геолокации.
 */
final readonly class GeoLocationConfig
{
    /**
     * @param string $dbPath Путь к файлу базы данных IP2Location
     * @param string $downloadToken Токен для скачивания обновлений базы данных
     * @param string $downloadUrl URL для скачивания обновлений базы данных
     * @param string $databaseCode Код базы данных для скачивания
     * @param int $cacheTtl Время жизни кеша в секундах
     */
    public function __construct(
        public string $dbPath,
        public string $downloadToken,
        public string $downloadUrl,
        public string $databaseCode,
        public int $cacheTtl = 3600, // 1 час по умолчанию
    ) {}

    /**
     * Создает конфигурацию из массива параметров.
     *
     * @param array{
     *    db_path?: string,
     *    download_token?: string,
     *    download_url?: string,
     *    database_code?: string,
     *    cache_ttl?: int,
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            dbPath: $config['db_path'] ?? __DIR__ . '/../../../db/geoip/IP2LOCATION-LITE-DB11.BIN',
            downloadToken: $config['download_token'] ?? '',
            downloadUrl: $config['download_url'] ?? 'https://www.ip2location.com/download',
            databaseCode: $config['database_code'] ?? 'DB11LITEBIN',
            cacheTtl: $config['cache_ttl'] ?? 3600,
        );
    }

    /**
     * Возвращает полный URL для скачивания базы данных.
     */
    public function getDownloadUrl(): string
    {
        return \sprintf(
            '%s/?token=%s&file=%s',
            rtrim($this->downloadUrl, '/'),
            $this->downloadToken,
            $this->databaseCode,
        );
    }
}
