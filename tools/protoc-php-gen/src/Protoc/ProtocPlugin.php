<?php

declare(strict_types=1);

namespace ProtoPhpGen\Protoc;

/**
 * Базовый класс для плагина протокомпилятора.
 */
abstract readonly class ProtocPlugin
{
    /**
     * Обрабатывает запрос протокомпилятора и возвращает ответ.
     *
     * @param PluginRequest $request Запрос протокомпилятора
     * @return PluginResponse Ответ протокомпилятору
     */
    abstract public function process(PluginRequest $request): PluginResponse;

    /**
     * Запускает плагин протокомпилятора.
     *
     * @return int Код завершения (0 - успех, не 0 - ошибка)
     */
    final public function run(): int
    {
        try {
            // Получаем бинарные данные из stdin
            $rawInput = file_get_contents('php://stdin');

            if ($rawInput === false) {
                throw new \RuntimeException('Failed to read input from stdin');
            }

            // Создаем объект запроса
            $request = PluginRequest::fromStdin($rawInput);

            // Обрабатываем запрос
            $response = $this->process($request);

            // Выводим ответ в stdout
            $response->write();

            return 0; // Успех
        } catch (\Throwable $e) {
            // В случае ошибки создаем ответ с сообщением об ошибке
            $response = new PluginResponse();
            $error = \sprintf(
                'Ошибка плагина protoc-php-gen: %s в %s на строке %d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            );
            $response->setError($error);
            $response->write();

            // В режиме отладки выводим стек вызовов в stderr
            if (getenv('PROTOC_PHP_GEN_DEBUG') === 'true') {
                fwrite(STDERR, $error . PHP_EOL);
                fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
            }

            return 1; // Ошибка
        }
    }
}
