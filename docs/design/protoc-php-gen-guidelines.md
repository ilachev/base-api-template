# Protoc PHP Generator Guidelines

This document provides guidance for using the `protoc-php-gen` tool, which focuses specifically on generating hydrators for transforming between domain entities and Protobuf messages.

## Design Philosophy

Our approach follows Clean Architecture principles, maintaining a clear separation between domain models and API contracts. The key philosophical points:

1. **Domain entities should be independent** of external protocols and representations
2. **Proto schema defines API contracts**, not internal domain structure
3. **Hydrators bridge the gap** between domain models and proto messages
4. **Manual domain modeling** provides greater flexibility and control

By generating only hydrators rather than complete entities and repositories, we maintain full control over our domain model while automating the repetitive transformation logic.

## Attribute-Based Mapping

Domain entities use PHP 8 attributes to define mappings to Protobuf fields:

```php
use App\Tools\ProtocPhpGen\Attributes\ProtoMapping;
use App\Tools\ProtocPhpGen\Attributes\ProtoField;

#[ProtoMapping(message: "App.Api.V1.UserMessage")]
final class User implements Entity
{
    public function __construct(
        #[ProtoField(name: "id")]
        private string $id,
        
        #[ProtoField(name: "email")]
        private string $email,
        
        #[ProtoField(name: "display_name")]
        private string $displayName,
        
        #[ProtoField(name: "created_at", type: "timestamp")]
        private \DateTimeImmutable $createdAt,
    ) {
    }
    
    // Domain-specific methods and behaviors...
}
```

## Attribute Types

The `ProtoField` attribute supports various transformation types:

| Type        | Description                                      | Proto Type                    | PHP Type               |
|-------------|--------------------------------------------------|-------------------------------|------------------------|
| `default`   | Standard mapping with type conversion            | any                           | matching PHP type      |
| `timestamp` | Converts timestamps between formats              | int64                         | \DateTimeImmutable     |
| `datetime`  | Similar to timestamp with different format       | int64                         | \DateTimeImmutable     |
| `json`      | Handles JSON serialization/deserialization       | string                        | array or object        |
| `enum`      | Maps between enum values                         | enum                          | PHP enum or string     |
| `custom`    | Uses a custom transformer                        | any                           | any                    |

## Hydrator Generation

Generated hydrators provide two primary methods:

1. `hydrate()` - Converts a Protobuf message to a domain entity
2. `extract()` - Converts a domain entity to a Protobuf message

The generator creates specialized hydrators that avoid using reflection at runtime, providing excellent performance for high-throughput API servers.

## Best Practices

### Domain Model Design

1. **Keep domain models focused on business logic**:
   - Include only properties relevant to your domain
   - Define domain methods and behaviors independent of API concerns
   - Avoid leaking API-specific details into domain models

2. **Use value objects** for rich domain concepts rather than primitive types

3. **Consider nullability carefully**:
   - Make explicit decisions about nullable vs non-nullable fields
   - Don't copy Proto3's default values approach uncritically

### Mapping Strategy

1. **Map selectively**:
   - Not every domain property needs to be exposed in the API
   - Not every API field needs to be in your domain model

2. **Use transformation types appropriately**:
   - Choose the correct transformation type for each field
   - Implement custom transformers for complex conversions

3. **Balance consistency and specialization**:
   - Maintain consistent naming patterns between domains and APIs
   - Don't force 1:1 mappings when inappropriate

### Evolution Management

1. **API versioning**:
   - Create new proto messages for major changes
   - Maintain backwards compatibility in API changes 

2. **Evolve domain models independently**:
   - Refine domain models based on business needs
   - Update mappings when changes affect the API contract

3. **Testing**:
   - Write tests for hydrators to ensure correct transformation
   - Test domain models independently from API representations

## Limitations and Future Improvements

Current limitations of the hydrator-only approach:

1. Manual synchronization required between domain models and proto definitions
2. Limited support for complex nested structures and maps
3. No automatic validation of mapping correctness

Planned improvements:

1. Enhanced validation during generation to catch mapping errors
2. Better support for complex types including nested messages and maps
3. Improved documentation and example generation
4. Performance optimizations for large scale message processing

## Conclusion

By focusing exclusively on hydrator generation, we maintain the benefits of Clean Architecture while reducing the repetitive work of implementing transformation logic. This approach gives us the best of both worlds: full control over our domain model with automated conversions between domain entities and API contracts.