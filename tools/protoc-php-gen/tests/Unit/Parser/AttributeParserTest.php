<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Attributes\ProtoField;
use ProtoPhpGen\Attributes\ProtoMapping;
use ProtoPhpGen\Parser\AttributeParser;

/**
 * @covers \ProtoPhpGen\Parser\AttributeParser
 */
class AttributeParserTest extends TestCase
{
    private AttributeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AttributeParser();
    }

    public function testParseReturnsNullForNonExistentClass(): void
    {
        $result = $this->parser->parse('NonExistentClass');
        self::assertNull($result);
    }

    public function testParseReturnsNullForClassWithoutProtoMapping(): void
    {
        $result = $this->parser->parse(TestClassWithoutMapping::class);
        self::assertNull($result);
    }

    public function testParseCreatesClassMappingForClassWithProtoMapping(): void
    {
        $result = $this->parser->parse(TestEntityWithMapping::class);
        
        self::assertNotNull($result);
        self::assertEquals(TestEntityWithMapping::class, $result->getDomainClass());
        self::assertEquals('App\\Api\\TestProto', $result->getProtoClass());
        self::assertEquals('TestCustomTransformer', $result->getTransformerClass());
    }

    public function testParseCreatesFieldMappingsForPropertiesWithProtoField(): void
    {
        $result = $this->parser->parse(TestEntityWithMapping::class);
        
        self::assertNotNull($result);
        $fieldMappings = $result->getFieldMappings();
        
        self::assertCount(3, $fieldMappings);
        
        // Check first field mapping
        self::assertEquals('id', $fieldMappings[0]->getDomainProperty());
        self::assertEquals('id', $fieldMappings[0]->getProtoField());
        self::assertEquals('default', $fieldMappings[0]->getType());
        self::assertNull($fieldMappings[0]->getTransformer());
        
        // Check second field mapping
        self::assertEquals('name', $fieldMappings[1]->getDomainProperty());
        self::assertEquals('user_name', $fieldMappings[1]->getProtoField());
        self::assertEquals('default', $fieldMappings[1]->getType());
        self::assertNull($fieldMappings[1]->getTransformer());
        
        // Check third field mapping with custom transformer
        self::assertEquals('createdAt', $fieldMappings[2]->getDomainProperty());
        self::assertEquals('created_at', $fieldMappings[2]->getProtoField());
        self::assertEquals('datetime', $fieldMappings[2]->getType());
        self::assertEquals('dateTimeTransformer', $fieldMappings[2]->getTransformer());
    }
}

// Test classes for the parser

class TestClassWithoutMapping
{
    private int $id;
    private string $name;
}

#[ProtoMapping(class: 'App\\Api\\TestProto', transformerClass: 'TestCustomTransformer')]
class TestEntityWithMapping
{
    #[ProtoField(name: 'id')]
    private int $id;
    
    #[ProtoField(name: 'user_name')]
    private string $name;
    
    #[ProtoField(name: 'created_at', type: 'datetime', transformer: 'dateTimeTransformer')]
    private \DateTime $createdAt;
    
    private string $notMapped;
}
