# План генерации кода для замены рефлексии

## Проблема

Использование рефлексии в гидраторах влияет на производительность, особенно в условиях долгоживущих процессов RoadRunner. Даже с применением LRUReflectionCache, сам механизм рефлексии требует значительных ресурсов.

## Предлагаемое решение

Полностью заменить использование рефлексии во время выполнения на скомпилированный код, генерируемый из proto-файлов. Это включает в себя создание:

1. Сгенерированных сущностей (Entity)
2. Специализированных гидраторов для каждого типа
3. Базовых репозиториев для работы с сущностями

## Структура проекта

```
project/
  ├── tools/                      # Инструменты для проекта
  │   └── protoc-php-gen/         # Плагин для protoc (переименовано чтобы избежать конфликтов)
  │       ├── bin/                # Исполняемые скрипты
  │       │   └── protoc-php-gen  # Главная точка входа
  │       ├── src/                # Код плагина
  │       │   ├── Generator/      # Генераторы разных типов кода
  │       │   │   ├── EntityGenerator.php
  │       │   │   ├── HydratorGenerator.php
  │       │   │   └── RepositoryGenerator.php
  │       │   ├── Parser/         # Парсеры прото-файлов
  │       │   │   └── ProtoParser.php
  │       │   ├── Builder/        # Построители PHP-кода
  │       │   │   ├── ClassBuilder.php
  │       │   │   └── FileBuilder.php
  │       │   └── Config/         # Конфигурация плагина
  │       │       └── GeneratorConfig.php
  │       └── composer.json       # Зависимости плагина
  ├── gen/                        # Выходной каталог для сгенерированного кода
  │   ├── Domain/                 # Сгенерированные доменные модели
  │   └── Infrastructure/         # Сгенерированные гидраторы и репозитории
  └── composer.json               # Основной composer.json проекта
```

## Реализация плагина

### Основной скрипт (bin/protoc-php-gen)

```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use ProtoPhpGen\Generator\CodeGeneratorFactory;
use ProtoPhpGen\Parser\ProtoParser;
use ProtoPhpGen\Config\GeneratorConfig;

// Чтение CodeGeneratorRequest из stdin
$input = file_get_contents('php://stdin');
$request = new Google\Protobuf\Compiler\CodeGeneratorRequest();
$request->mergeFromString($input);

// Парсинг запроса и генерация файлов
$parser = new ProtoParser();
$config = new GeneratorConfig();
$generatorFactory = new CodeGeneratorFactory($config);

$response = new Google\Protobuf\Compiler\CodeGeneratorResponse();

// Обработка proto-файлов
foreach ($request->getProtoFile() as $protoFile) {
    $descriptors = $parser->parse($protoFile);
    
    foreach ($descriptors as $descriptor) {
        $generator = $generatorFactory->createGenerator($descriptor->getType());
        $files = $generator->generate($descriptor);
        
        foreach ($files as $file) {
            $responseFile = new Google\Protobuf\Compiler\CodeGeneratorResponse\File();
            $responseFile->setName($file->getName());
            $responseFile->setContent($file->getContent());
            $response->addFile($responseFile);
        }
    }
}

// Отправка ответа
echo $response->serializeToString();
```

### Конфигурация генератора (Config/GeneratorConfig.php)

```php
<?php
namespace ProtoPhpGen\Config;

class GeneratorConfig
{
    public function __construct(
        private string $namespace = 'App\Gen',
        private string $outputDir = 'gen',
        private string $entityInterface = 'App\Domain\Entity',
        private bool $generateRepositories = true,
        private bool $generateHydrators = true,
    ) {}
    
    // Getters и fluent setters
}
```

### Генератор сущностей с использованием nette/php-generator

```php
<?php
namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;

class EntityGenerator implements Generator
{
    private PsrPrinter $printer;
    
    public function __construct(
        private GeneratorConfig $config,
    ) {
        $this->printer = new PsrPrinter();
    }

    public function generate(EntityDescriptor $descriptor): array
    {
        // Создаем файл
        $file = new PhpFile();
        $file->setStrictTypes();
        
        // Добавляем namespace
        $namespace = $file->addNamespace($this->config->getNamespace() . '\Domain');
        $namespace->addUse($this->config->getEntityInterface());
        
        // Создаем класс
        $class = $namespace->addClass($descriptor->getName());
        $class->setFinal(true)
              ->setReadOnly(true)
              ->addImplement($this->getShortName($this->config->getEntityInterface()));
        
        // Добавляем конструктор
        $constructor = $class->addMethod('__construct');
        
        // Добавляем свойства
        foreach ($descriptor->getProperties() as $property) {
            $prop = $class->addProperty($property->name)
                ->setPublic()
                ->setType($property->type);
                
            if ($property->nullable) {
                $prop->setNullable(true);
            }
            
            // Добавляем параметр в конструктор
            $param = $constructor->addParameter($property->name)
                ->setType($property->type);
                
            if ($property->nullable) {
                $param->setNullable(true);
            }
        }
        
        // Генерируем код
        $content = $this->printer->printFile($file);
        
        $filePath = $this->config->getOutputDir() . '/Domain/' . 
                  $descriptor->getName() . '.php';
        
        return [new GeneratedFile($filePath, $content)];
    }
    
    private function getShortName(string $fullClassName): string
    {
        $parts = explode('\\', $fullClassName);
        return end($parts);
    }
}
```

### Генератор гидраторов

```php
<?php
namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Config\GeneratorConfig; 
use ProtoPhpGen\Model\EntityDescriptor;

class HydratorGenerator implements Generator
{
    private PsrPrinter $printer;
    
    public function __construct(
        private GeneratorConfig $config,
    ) {
        $this->printer = new PsrPrinter();
    }

    public function generate(EntityDescriptor $descriptor): array
    {
        $file = new PhpFile();
        $file->setStrictTypes();
        
        $namespace = $file->addNamespace($this->config->getNamespace() . '\Infrastructure\Hydrator');
        $namespace->addUse($this->config->getNamespace() . '\Domain\\' . $descriptor->getName());
        $namespace->addUse('App\Infrastructure\Hydrator\TypedHydrator');
        
        $class = $namespace->addClass($descriptor->getName() . 'Hydrator');
        $class->setFinal(true)
              ->addImplement('TypedHydrator');
              
        // Метод getEntityClass
        $getEntityClass = $class->addMethod('getEntityClass')
            ->setReturnType('string')
            ->setBody('return ' . $descriptor->getName() . '::class;');
            
        // Метод hydrate
        $hydrateMethod = $class->addMethod('hydrate')
            ->setReturnType($descriptor->getName())
            ->addParameter('data')
            ->setType('array');
            
        $hydrateBody = "return new " . $descriptor->getName() . "(\n";
        foreach ($descriptor->getProperties() as $property) {
            $defaultValue = $property->nullable ? 'null' : 
                ($property->type === 'string' ? "''" : '0');
            
            $hydrateBody .= "    " . $property->name . ": \$data['" . $property->name . 
                "'] ?? " . $defaultValue . ",\n";
        }
        $hydrateBody .= ");";
        $hydrateMethod->setBody($hydrateBody);
        
        // Метод extract
        $extractMethod = $class->addMethod('extract')
            ->setReturnType('array')
            ->addParameter('entity')
            ->setType('object');
            
        $extractBody = "return [\n";
        foreach ($descriptor->getProperties() as $property) {
            $extractBody .= "    '" . $property->name . "' => \$entity->" . 
                $property->name . ",\n";
        }
        $extractBody .= "];";
        $extractMethod->setBody($extractBody);
        
        // Генерируем код
        $content = $this->printer->printFile($file);
        
        $filePath = $this->config->getOutputDir() . '/Infrastructure/Hydrator/' . 
                  $descriptor->getName() . 'Hydrator.php';
        
        return [new GeneratedFile($filePath, $content)];
    }
}
```

## Интеграция в проект

### Зависимости в composer.json для плагина

```json
{
    "require": {
        "php": ">=8.1",
        "google/protobuf": "^3.0",
        "nette/php-generator": "^4.0",
        "symfony/console": "^6.0",
        "nikic/php-parser": "^4.15"
    }
}
```

### Добавление задачи в taskfile.yaml

```yaml
proto:gen:entities:
  desc: Generate domain entities from proto files
  cmds:
    - mkdir -p gen
    - >
      protoc -I={{.PROTO_DIR}}
      --plugin=protoc-php-gen=./tools/protoc-php-gen/bin/protoc-php-gen
      --php-gen_out=gen
      {{.PROTO_DIR}}/app/domain/*.proto
```

### Регистрация сгенерированного кода в composer.json

```json
"autoload": {
    "psr-4": {
        "App\\": "src/",
        "App\\Gen\\": "gen/"
    }
}
```

## Пример расширения proto-файлов

```protobuf
syntax = "proto3";

package app.domain;

import "google/protobuf/descriptor.proto";

option php_namespace = "App\\Domain\\Session";

extend google.protobuf.MessageOptions {
  optional bool is_entity = 1000 [default = false];
  optional string table_name = 1001;
  optional string primary_key = 1002 [default = "id"];
}

message Session {
  option (is_entity) = true;
  option (table_name) = "sessions";
  option (primary_key) = "id";

  string id = 1;
  optional int64 user_id = 2;
  string payload = 3;
  int64 expires_at = 4;
  int64 created_at = 5;
  int64 updated_at = 6;
}
```

## Пример сгенерированного кода

### Сущность

```php
<?php

declare(strict_types=1);

namespace App\Gen\Domain\Session;

use App\Domain\Entity;

final readonly class Session implements Entity
{
    public function __construct(
        public string $id,
        public ?int $userId,
        public string $payload,
        public int $expiresAt,
        public int $createdAt,
        public int $updatedAt,
    ) {}
}
```

### Гидратор

```php
<?php

declare(strict_types=1);

namespace App\Gen\Infrastructure\Hydrator;

use App\Gen\Domain\Session\Session;
use App\Infrastructure\Hydrator\TypedHydrator;

final class SessionHydrator implements TypedHydrator
{
    public function getEntityClass(): string 
    {
        return Session::class;
    }
    
    public function hydrate(array $data): Session 
    {
        return new Session(
            id: $data['id'] ?? '',
            userId: $data['userId'] ?? null,
            payload: $data['payload'] ?? '',
            expiresAt: $data['expiresAt'] ?? 0,
            createdAt: $data['createdAt'] ?? 0,
            updatedAt: $data['updatedAt'] ?? 0
        );
    }
    
    public function extract(object $entity): array 
    {
        return [
            'id' => $entity->id,
            'userId' => $entity->userId,
            'payload' => $entity->payload,
            'expiresAt' => $entity->expiresAt,
            'createdAt' => $entity->createdAt,
            'updatedAt' => $entity->updatedAt
        ];
    }
}
```

### Реестр гидраторов

```php
<?php

declare(strict_types=1);

namespace App\Gen\Infrastructure\Hydrator;

use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\HydratorException;

final class CompiledHydrator implements Hydrator
{
    private array $hydrators = [];
    
    public function __construct()
    {
        $this->registerHydrators();
    }
    
    private function registerHydrators(): void
    {
        $this->hydrators = [
            \App\Gen\Domain\Session\Session::class => new SessionHydrator(),
            // Другие гидраторы...
        ];
    }
    
    public function hydrate(string $className, array $data): object
    {
        if (!isset($this->hydrators[$className])) {
            throw new HydratorException("No hydrator found for class: {$className}");
        }
        
        return $this->hydrators[$className]->hydrate($data);
    }
    
    public function extract(object $object): array
    {
        $className = $object::class;
        
        if (!isset($this->hydrators[$className])) {
            throw new HydratorException("No hydrator found for class: {$className}");
        }
        
        return $this->hydrators[$className]->extract($object);
    }
}
```

## План перемещения в отдельную библиотеку

1. Создать отдельный репозиторий для protoc-php-gen
2. Добавить тесты и документацию
3. Улучшить генераторы кода и конфигурацию
4. Публикация в Packagist с пространством имён вашей организации

## Преимущества подхода

1. Полное исключение рефлексии во время выполнения
2. Значительное повышение производительности
3. Статическая типизация всех компонентов
4. Единый источник истины для моделей (proto-файлы)
5. Современные инструменты генерации кода (nette/php-generator)
6. Удобное API и строгая типизация сгенерированного кода
7. Легкая интеграция и расширяемость
8. Совместимость с существующим кодом через общие интерфейсы