## Conventions

### DTO
The project uses a unified approach to creating DTOs - only public properties through promoted constructor:

```php
final readonly class Session
{
    public function __construct(
        public string $id,
        public string $token
    ) {}
}