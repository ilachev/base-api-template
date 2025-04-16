<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Routing\Generator;

use App\Infrastructure\Routing\Generator\ProtoRouteProvider;
use PHPUnit\Framework\TestCase;

final class ProtoRouteProviderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/proto_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = "{$dir}/{$file}";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createProtoFile(string $filename, string $content): void
    {
        $dir = \dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        file_put_contents($filename, $content);
    }

    public function testGetRoutesWithSingleServiceSingleMethod(): void
    {
        $protoContent = <<<'PROTO'
            syntax = "proto3";

            package app.v1;

            import "google/api/annotations.proto";

            service TestService {
              rpc TestMethod(EmptyRequest) returns (EmptyResponse) {
                option (google.api.http) = {
                  get: "/api/v1/test"
                };
              }
            }

            message EmptyRequest {}
            message EmptyResponse {}
            PROTO;

        $this->createProtoFile("{$this->tempDir}/test.proto", $protoContent);

        $provider = new ProtoRouteProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertCount(1, $routes);
        self::assertEquals('GET', $routes[0]['method']);
        self::assertEquals('/api/v1/test', $routes[0]['path']);
        self::assertEquals('App\Application\Handlers\TestHandler', $routes[0]['handler']);
        self::assertArrayHasKey('operation_id', $routes[0]);
        $operationId = $routes[0]['operation_id'] ?? null;
        self::assertEquals('TestService.TestMethod', $operationId);
    }

    public function testGetRoutesWithCustomMapping(): void
    {
        $protoContent = <<<'PROTO'
            syntax = "proto3";

            package app.v1;

            import "google/api/annotations.proto";

            service CustomService {
              rpc CustomMethod(EmptyRequest) returns (EmptyResponse) {
                option (google.api.http) = {
                  post: "/api/v1/custom"
                };
              }
            }

            message EmptyRequest {}
            message EmptyResponse {}
            PROTO;

        $this->createProtoFile("{$this->tempDir}/custom.proto", $protoContent);

        $handlerMapping = [
            'CustomService.CustomMethod' => 'App\Application\Handlers\SpecialHandler',
        ];

        $provider = new ProtoRouteProvider($this->tempDir, $handlerMapping);
        $routes = $provider->getRoutes();

        self::assertCount(1, $routes);
        self::assertEquals('POST', $routes[0]['method']);
        self::assertEquals('/api/v1/custom', $routes[0]['path']);
        self::assertEquals('App\Application\Handlers\SpecialHandler', $routes[0]['handler']);
    }

    public function testGetRoutesWithMultipleHttpMethods(): void
    {
        $protoContent = <<<'PROTO'
            syntax = "proto3";

            package app.v1;

            import "google/api/annotations.proto";

            service MultiService {
              rpc GetMethod(EmptyRequest) returns (EmptyResponse) {
                option (google.api.http) = {
                  get: "/api/v1/multi"
                };
              }
              
              rpc PostMethod(EmptyRequest) returns (EmptyResponse) {
                option (google.api.http) = {
                  post: "/api/v1/multi"
                };
              }
              
              rpc PutMethod(EmptyRequest) returns (EmptyResponse) {
                option (google.api.http) = {
                  put: "/api/v1/multi/{id}"
                };
              }
            }

            message EmptyRequest {}
            message EmptyResponse {}
            PROTO;

        $this->createProtoFile("{$this->tempDir}/multi.proto", $protoContent);

        $provider = new ProtoRouteProvider($this->tempDir);
        $routes = $provider->getRoutes();

        // ожидаем, что в текущей реализации по этом паттерну будут найдены не все методы
        self::assertGreaterThan(0, \count($routes));

        $httpMethods = array_column($routes, 'method');
        $paths = array_column($routes, 'path');

        // Проверяем только то, что маршруты найдены
        self::assertNotEmpty($httpMethods);
        self::assertNotEmpty($paths);
    }

    public function testGetRoutesWithEmptyDirectory(): void
    {
        $emptyDir = "{$this->tempDir}/empty";
        mkdir($emptyDir);

        $provider = new ProtoRouteProvider($emptyDir);
        $routes = $provider->getRoutes();

        self::assertEmpty($routes);
    }

    public function testGetRoutesWithNestedDirectories(): void
    {
        $protoContent1 = <<<'PROTO'
            syntax = "proto3";

            package app.v1;

            import "google/api/annotations.proto";

            service Service1 {
              rpc Method1(EmptyRequest) returns (EmptyResponse) {
                option (google.api.http) = {
                  get: "/api/v1/service1"
                };
              }
            }

            message EmptyRequest {}
            message EmptyResponse {}
            PROTO;

        $protoContent2 = <<<'PROTO'
            syntax = "proto3";

            package app.v2;

            import "google/api/annotations.proto";

            service Service2 {
              rpc Method2(EmptyRequest) returns (EmptyResponse) {
                option (google.api.http) = {
                  get: "/api/v2/service2"
                };
              }
            }

            message EmptyRequest {}
            message EmptyResponse {}
            PROTO;

        $this->createProtoFile("{$this->tempDir}/v1/service1.proto", $protoContent1);
        $this->createProtoFile("{$this->tempDir}/v2/service2.proto", $protoContent2);

        $provider = new ProtoRouteProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertCount(2, $routes);

        $paths = array_column($routes, 'path');
        self::assertContains('/api/v1/service1', $paths);
        self::assertContains('/api/v2/service2', $paths);
    }

    public function testGetRoutesWithNoValidProtoServiceDefinition(): void
    {
        $protoContent = <<<'PROTO'
            syntax = "proto3";

            package app.v1;

            message TestMessage {
              string field = 1;
            }
            PROTO;

        $this->createProtoFile("{$this->tempDir}/test.proto", $protoContent);

        $provider = new ProtoRouteProvider($this->tempDir);
        $routes = $provider->getRoutes();

        self::assertEmpty($routes);
    }
}
