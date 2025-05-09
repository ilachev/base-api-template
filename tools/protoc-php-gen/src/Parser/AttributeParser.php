<?php

declare(strict_types=1);

namespace ProtoPhpGen\Parser;

use ProtoPhpGen\Attributes\ProtoField;
use ProtoPhpGen\Attributes\ProtoMapping;
use ProtoPhpGen\Model\ClassMapping;
use ProtoPhpGen\Model\FieldMapping;

/**
 * Parser for extracting mapping information from attributes.
 */
final class AttributeParser
{
    /**
     * Parse class and its properties to create a mapping.
     */
    public function parse(string $className): ?ClassMapping
    {
        if (!class_exists($className)) {
            return null;
        }

        $reflectionClass = new \ReflectionClass($className);
        $protoMappingAttrs = $reflectionClass->getAttributes(ProtoMapping::class);
        
        if (empty($protoMappingAttrs)) {
            return null;
        }
        
        /** @var ProtoMapping $protoMapping */
        $protoMapping = $protoMappingAttrs[0]->newInstance();
        $classMapping = new ClassMapping(
            $className,
            $protoMapping->class,
            $protoMapping->transformerClass,
        );
        
        foreach ($reflectionClass->getProperties() as $property) {
            $protoFieldAttrs = $property->getAttributes(ProtoField::class);
            
            if (empty($protoFieldAttrs)) {
                continue;
            }
            
            /** @var ProtoField $protoField */
            $protoField = $protoFieldAttrs[0]->newInstance();
            
            $fieldMapping = new FieldMapping(
                $property->getName(),
                $protoField->name,
                $protoField->type,
                $protoField->transformer,
                $protoField->options,
            );
            
            $classMapping->addFieldMapping($fieldMapping);
        }
        
        return $classMapping;
    }
}
