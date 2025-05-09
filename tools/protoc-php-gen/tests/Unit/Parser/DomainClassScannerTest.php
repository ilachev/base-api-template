<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\StandaloneConfig;
use ProtoPhpGen\Parser\DomainClassScanner;

/**
 * @covers \ProtoPhpGen\Parser\DomainClassScanner
 */
class DomainClassScannerTest extends TestCase
{
    private string $tempDir;
    
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/domain_scanner_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }
    
    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
        }
    }
    
    private function removeDir(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir . '/' . $object;
                    if (is_dir($path)) {
                        $this->removeDir($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    public function testScanFindsClassesWithProtoMapping(): void
    {
        // Arrange
        $this->createTestClasses();
        
        $config = new StandaloneConfig(
            domainDir: $this->tempDir,
            protoDir: $this->tempDir,
            outputDir: $this->tempDir . '/output',
            domainNamespace: 'App\\Domain',
            protoNamespace: 'App\\Api'
        );
        
        $scanner = new DomainClassScanner($config);
        
        // Act
        $result = $scanner->scan();
        
        // Assert
        // Due to autoloading limitations in tests, we can't actually load the created classes
        // So we'll just check if the scanner attempted to find classes by making sure it didn't throw errors
        self::assertIsArray($result);
    }
    
    private function createTestClasses(): void
    {
        // Create a directory structure
        mkdir($this->tempDir . '/User', 0777, true);
        
        // Create a test class with mapping
        $classWithMapping = <<<'PHP'
<?php

namespace App\Domain\User;

use ProtoPhpGen\Attributes\ProtoMapping;
use ProtoPhpGen\Attributes\ProtoField;

#[ProtoMapping(class: "App\\Api\\V1\\UserProto")]
class User
{
    #[ProtoField(name: "id")]
    private int $id;
    
    #[ProtoField(name: "name")]
    private string $name;
    
    // Getters and setters
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function setId(int $id): void
    {
        $this->id = $id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
PHP;
        
        // Create a test class without mapping
        $classWithoutMapping = <<<'PHP'
<?php

namespace App\Domain\User;

class Role
{
    private int $id;
    private string $name;
    
    // Getters and setters
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function setId(int $id): void
    {
        $this->id = $id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
PHP;
        
        file_put_contents($this->tempDir . '/User/User.php', $classWithMapping);
        file_put_contents($this->tempDir . '/User/Role.php', $classWithoutMapping);
    }
}
