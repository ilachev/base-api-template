<?php

/**
 * Конфигурация для библиотеки protoc-php-gen.
 * Этот файл позволяет кастомизировать генерируемый код и используемые интерфейсы.
 */
return [
    // Базовое пространство имен для генерируемых классов
    'namespace' => 'App\Gen',
    
    // Директория для генерируемых файлов
    'output_dir' => 'gen',
    
    // Интерфейс сущности
    'entity_interface' => 'App\Domain\Entity',
    
    // Интерфейс для гидраторов (null - не использовать интерфейс)
    'hydrator_interface' => null, // было: 'App\Infrastructure\Hydrator\TypedHydrator'
    
    // Базовый класс для репозиториев (null - не наследоваться)
    'repository_base_class' => null, // было: 'App\Infrastructure\Storage\Repository\AbstractRepository'
    
    // Класс хранилища для конструктора репозитория
    'storage_class' => null, // было: 'App\Infrastructure\Storage\Storage'
    
    // Класс гидратора для конструктора репозитория
    'hydrator_class' => null, // было: 'App\Infrastructure\Hydrator\Hydrator'
    
    // Генерировать репозитории
    'generate_repositories' => true,
    
    // Генерировать гидраторы
    'generate_hydrators' => true,
    
    // Автономный режим - не использовать внешние интерфейсы и классы
    'standalone_mode' => true,
];
