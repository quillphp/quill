<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use Quill\App;
use Quill\OpenApi;
use Quill\DTO;

class MockOpenApiDTO extends DTO
{
    public string $name;
    public ?int $age;
}

class MockOpenApiHandler
{
    /** @return array<string, mixed> */
    public function store(MockOpenApiDTO $dto): array
    {
        return ['ok' => true];
    }
}

class OpenApiTest extends TestCase
{
    public function testGenerateSchema(): void
    {
        $app = new App(['docs' => true]);
        $app->post('/users', [MockOpenApiHandler::class, 'store']);
        
        $gen = new OpenApi();
        $schema = $gen->generate($app->getHandlers());

        $this->assertEquals('3.1.0', $schema['openapi']);
        $paths = is_array($schema['paths']) ? $schema['paths'] : [];
        $this->assertArrayHasKey('/users', $paths);
        
        $userPath = is_array($paths['/users']) ? $paths['/users'] : [];
        $this->assertArrayHasKey('post', $userPath);
        
        $components = is_array($schema['components']) ? $schema['components'] : [];
        $schemas = is_array($components['schemas']) ? $components['schemas'] : [];
        $this->assertArrayHasKey('MockOpenApiDTO', $schemas);
        
        $dtoSchema = is_array($schemas['MockOpenApiDTO']) ? $schemas['MockOpenApiDTO'] : [];
        $properties = is_array($dtoSchema['properties']) ? $dtoSchema['properties'] : [];
        
        $nameProp = is_array($properties['name']) ? $properties['name'] : [];
        $ageProp = is_array($properties['age']) ? $properties['age'] : [];

        $this->assertEquals('string', $nameProp['type']);
        $this->assertEquals('integer', $ageProp['type']);
        $this->assertEquals(true, $ageProp['nullable']);
    }
}
