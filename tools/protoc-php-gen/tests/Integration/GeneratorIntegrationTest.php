<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;
use ProtoPhpGen\PhpGeneratorPlugin;
use ProtoPhpGen\Protoc\PluginRequest;

/**
 * Интеграционные тесты для генератора кода.
 * Проверяют весь цикл работы, от парсинга proto-файлов до генерации готовых файлов.
 */
final class GeneratorIntegrationTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/Fixtures';
    private const OUTPUT_DIR = __DIR__ . '/../../var/generated';

    /**
     * Выполняется перед каждым тестом.
     */
    protected function setUp(): void
    {
        // Создаем директорию для сгенерированных файлов, если она не существует
        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0755, true);
        }

        // Очищаем директорию перед каждым тестом
        $files = glob(self::OUTPUT_DIR . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->removeDirectory($file);
            }
        }
    }

    /**
     * Выполняется после каждого теста.
     */
    protected function tearDown(): void
    {
        // Можно оставить сгенерированные файлы для инспекции, если нужно
        // Или удалить их, чтобы не засорять файловую систему
        // $this->removeDirectory(self::OUTPUT_DIR);
    }

    /**
     * Тест генерации кода на основе proto-файла через ручное создание объектов.
     * Этот тест эмулирует ручное создание модели данных.
     */
    public function testManualGeneration(): void
    {
        // Arrange - создаем объекты вручную
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: self::OUTPUT_DIR,
            entityInterface: 'App\\Domain\\Entity',
            generateRepositories: true,
            generateHydrators: true,
        );

        // Создаем дескриптор сущности
        $descriptor = new EntityDescriptor(
            name: 'User',
            namespace: 'App\\Domain\\User',
            tableName: 'users',
            primaryKey: 'id',
        );

        // Добавляем свойства
        $descriptor->addProperty(new PropertyDescriptor(
            name: 'id',
            type: 'string',
            nullable: false,
            columnName: 'id',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'name',
            type: 'string',
            nullable: false,
            columnName: 'name',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'email',
            type: 'string',
            nullable: false,
            columnName: 'email',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'isActive',
            type: 'bool',
            nullable: false,
            columnName: 'is_active',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'roles',
            type: 'array',
            nullable: false,
            columnName: 'roles',
            repeated: true,
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'createdAt',
            type: 'int',
            nullable: false,
            columnName: 'created_at',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'updatedAt',
            type: 'int',
            nullable: false,
            columnName: 'updated_at',
        ));

        // Создаем экземпляр плагина
        $plugin = new PhpGeneratorPlugin();

        // Act - преобразуем дескриптор в запрос
        $request = $this->createRequestFromDescriptor($descriptor, $config);
        $response = $plugin->process($request);

        // Assert - проверяем результаты
        $files = $response->getFiles();
        self::assertNotEmpty($files);
        
        // Должно быть 3 файла: сущность, гидратор и репозиторий
        self::assertCount(3, $files);
        
        // Проверяем, что ошибок нет
        self::assertNull($response->getError());
        
        // Записываем файлы на диск для проверки
        foreach ($files as $file) {
            $path = $file->getName();
            $dir = dirname($path);
            
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            file_put_contents($path, $file->getContent());
            self::assertFileExists($path);
        }
        
        // Проверяем, что файлы сущности, гидратора и репозитория существуют
        self::assertFileExists(self::OUTPUT_DIR . '/Domain/User.php');
        self::assertFileExists(self::OUTPUT_DIR . '/Infrastructure/Hydrator/UserHydrator.php');
        self::assertFileExists(self::OUTPUT_DIR . '/Infrastructure/Storage/PostgreSQLUserRepository.php');
    }

    /**
     * Тест генерации кода на основе файлов proto.
     * Этот тест эмулирует реальное использование плагина через protoc.
     */
    public function testProtoFileGeneration(): void
    {
        // Arrange
        $protoFile = self::FIXTURES_DIR . '/user.proto';
        self::assertFileExists($protoFile);
        
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: self::OUTPUT_DIR,
            entityInterface: 'App\\Domain\\Entity',
            generateRepositories: true,
            generateHydrators: true,
        );
        
        // Создаем запрос на основе proto-файла
        $request = $this->createRequestFromProtoFile($protoFile, $config);
        
        // Act
        $plugin = new PhpGeneratorPlugin();
        $response = $plugin->process($request);
        
        // Assert
        self::assertNull($response->getError());
        $files = $response->getFiles();
        self::assertNotEmpty($files);
        
        // Записываем файлы на диск для проверки
        foreach ($files as $file) {
            $path = $file->getName();
            $dir = dirname($path);
            
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            file_put_contents($path, $file->getContent());
            self::assertFileExists($path);
        }
        
        // Проверяем, что файлы сущности, гидратора и репозитория существуют
        self::assertFileExists(self::OUTPUT_DIR . '/Domain/User.php');
        self::assertFileExists(self::OUTPUT_DIR . '/Infrastructure/Hydrator/UserHydrator.php');
        self::assertFileExists(self::OUTPUT_DIR . '/Infrastructure/Storage/PostgreSQLUserRepository.php');
        
        // Проверяем содержимое файла сущности
        $entityContent = file_get_contents(self::OUTPUT_DIR . '/Domain/User.php');
        self::assertStringContainsString('namespace App\\Gen\\Domain;', $entityContent);
        self::assertStringContainsString('final readonly class User implements Entity', $entityContent);
        self::assertStringContainsString('public string $id;', $entityContent);
        self::assertStringContainsString('public string $name;', $entityContent);
        self::assertStringContainsString('public string $email;', $entityContent);
        self::assertStringContainsString('public bool $isActive;', $entityContent);
        self::assertStringContainsString('public array $roles;', $entityContent);
        self::assertStringContainsString('public int $createdAt;', $entityContent);
        self::assertStringContainsString('public int $updatedAt;', $entityContent);
        
        // Проверяем конструктор
        self::assertStringContainsString('public function __construct(', $entityContent);
        self::assertStringContainsString('string $id,', $entityContent);
        self::assertStringContainsString('string $name,', $entityContent);
        self::assertStringContainsString('string $email,', $entityContent);
        self::assertStringContainsString('bool $isActive,', $entityContent);
        self::assertStringContainsString('array $roles,', $entityContent);
        self::assertStringContainsString('int $createdAt,', $entityContent);
        self::assertStringContainsString('int $updatedAt', $entityContent);
    }

    /**
     * Создает запрос на основе дескриптора сущности.
     */
    private function createRequestFromDescriptor(EntityDescriptor $descriptor, GeneratorConfig $config): PluginRequest
    {
        $request = new PluginRequest();
        
        // Устанавливаем параметры запроса
        $parameters = [
            'namespace' => $config->getNamespace(),
            'output_dir' => $config->getOutputDir(),
            'entity_interface' => $config->getEntityInterface(),
            'generate_repositories' => $config->shouldGenerateRepositories() ? 'true' : 'false',
            'generate_hydrators' => $config->shouldGenerateHydrators() ? 'true' : 'false',
        ];
        
        $request->setParameter(http_build_query($parameters));
        
        // Добавляем файл для генерации
        $request->addFileToGenerate('test.proto');
        
        // Создаем proto-файл с сообщением
        $protoFile = [
            'name' => 'test.proto',
            'package' => strtolower(str_replace('\\', '.', $descriptor->getNamespace())),
            'options' => [
                'php_namespace' => $descriptor->getNamespace(),
            ],
            'message_type' => [
                [
                    'name' => $descriptor->getName(),
                    'options' => [
                        'is_entity' => true,
                        'table_name' => $descriptor->getTableName(),
                        'primary_key' => $descriptor->getPrimaryKey(),
                    ],
                    'field' => array_map(function(PropertyDescriptor $property) {
                        static $index = 1;
                        
                        return [
                            'name' => $property->getColumnName(),
                            'type' => $this->phpTypeToProtoType($property->type),
                            'label' => $property->nullable ? 1 : ($property->repeated ? 3 : 2), // 1 = optional, 2 = required, 3 = repeated
                            'number' => $index++,
                        ];
                    }, $descriptor->getProperties()),
                ],
            ],
        ];
        
        $request->addProtoFile('test.proto', $protoFile);
        
        return $request;
    }

    /**
     * Преобразует PHP-тип в proto-тип.
     */
    private function phpTypeToProtoType(string $phpType): int
    {
        return match ($phpType) {
            'int' => 5,    // TYPE_INT32
            'float' => 2,  // TYPE_FLOAT
            'string' => 9, // TYPE_STRING
            'bool' => 8,   // TYPE_BOOL
            'array' => 9,  // Используем TYPE_STRING для массивов (repeated field)
            default => 11, // TYPE_MESSAGE для сложных типов
        };
    }

    /**
     * Создает запрос на основе proto-файла.
     * Эта функция эмулирует запрос от protoc к плагину.
     */
    private function createRequestFromProtoFile(string $protoFile, GeneratorConfig $config): PluginRequest
    {
        $request = new PluginRequest();
        
        // Устанавливаем параметры запроса
        $parameters = [
            'namespace' => $config->getNamespace(),
            'output_dir' => $config->getOutputDir(),
            'entity_interface' => $config->getEntityInterface(),
            'generate_repositories' => $config->shouldGenerateRepositories() ? 'true' : 'false',
            'generate_hydrators' => $config->shouldGenerateHydrators() ? 'true' : 'false',
        ];
        
        $request->setParameter(http_build_query($parameters));
        
        // Имя файла без пути
        $fileName = basename($protoFile);
        
        // Добавляем файл для генерации
        $request->addFileToGenerate($fileName);
        
        // Парсим proto-файл
        $content = file_get_contents($protoFile);
        
        // Простая эмуляция парсинга proto-файла
        // В реальном приложении здесь будет использоваться настоящий парсер proto-файлов
        
        // Извлекаем php_namespace
        preg_match('/option\s+php_namespace\s*=\s*"([^"]+)"\s*;/', $content, $namespaceMatches);
        $namespace = $namespaceMatches[1] ?? 'App\\Domain';
        
        // Извлекаем имя пакета
        preg_match('/package\s+([^;]+)\s*;/', $content, $packageMatches);
        $package = $packageMatches[1] ?? 'app.domain';
        
        // Извлекаем информацию о сообщении
        preg_match('/message\s+(\w+)\s*{([^}]+)}/s', $content, $messageMatches);
        $messageName = $messageMatches[1] ?? 'User';
        $messageBody = $messageMatches[2] ?? '';
        
        // Извлекаем опции сообщения
        preg_match('/option\s*\(table_name\)\s*=\s*"([^"]+)"\s*;/', $messageBody, $tableNameMatches);
        $tableName = $tableNameMatches[1] ?? strtolower($messageName) . 's';
        
        preg_match('/option\s*\(primary_key\)\s*=\s*"([^"]+)"\s*;/', $messageBody, $primaryKeyMatches);
        $primaryKey = $primaryKeyMatches[1] ?? 'id';
        
        // Извлекаем поля сообщения
        preg_match_all('/\s*(optional|repeated)?\s*(\w+)\s+(\w+)\s*=\s*(\d+)\s*;/', $messageBody, $fieldMatches, PREG_SET_ORDER);
        
        $fields = [];
        foreach ($fieldMatches as $index => $match) {
            $label = match ($match[1] ?? '') {
                'optional' => 1, // LABEL_OPTIONAL
                'repeated' => 3, // LABEL_REPEATED
                default => 2,    // LABEL_REQUIRED
            };
            
            $type = match ($match[2]) {
                'int32', 'int64', 'uint32', 'uint64', 'sint32', 'sint64', 'fixed32', 'fixed64', 'sfixed32', 'sfixed64' => 5, // TYPE_INT32
                'float', 'double' => 2, // TYPE_FLOAT
                'string' => 9, // TYPE_STRING
                'bool' => 8, // TYPE_BOOL
                'bytes' => 12, // TYPE_BYTES
                default => 11, // TYPE_MESSAGE для сложных типов
            };
            
            $fields[] = [
                'name' => $match[3],
                'type' => $type,
                'label' => $label,
                'number' => (int) $match[4],
            ];
        }
        
        // Создаем proto-файл
        $protoFileData = [
            'name' => $fileName,
            'package' => $package,
            'options' => [
                'php_namespace' => $namespace,
            ],
            'message_type' => [
                [
                    'name' => $messageName,
                    'options' => [
                        'is_entity' => true,
                        'table_name' => $tableName,
                        'primary_key' => $primaryKey,
                    ],
                    'field' => $fields,
                ],
            ],
        ];
        
        $request->addProtoFile($fileName, $protoFileData);
        
        return $request;
    }

    /**
     * Рекурсивно удаляет директорию и все ее содержимое.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}
