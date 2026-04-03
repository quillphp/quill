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
    public function store(MockOpenApiDTO $dto): array
    {
        return ['ok' => true];
    }
}

class OpenApiTest extends TestCase
{
    public function testGenerateSchema()
    {
        $app = new App(['docs' => true]);
        $app->post('/users', [MockOpenApiHandler::class, 'store']);
        
        $gen = new OpenApi();
        $schema = $gen->generate($app->getHandlers());

        $this->assertEquals('3.1.0', $schema['openapi']);
        $this->assertArrayHasKey('/users', $schema['paths']);
        $this->assertArrayHasKey('post', $schema['paths']['/users']);
        
        $this->assertArrayHasKey('MockOpenApiDTO', $schema['components']['schemas']);
        $properties = $schema['components']['schemas']['MockOpenApiDTO']['properties'];
        
        $this->assertEquals('string', $properties['name']['type']);
        $this->assertEquals('integer', $properties['age']['type']);
        $this->assertTrue($properties['age']['nullable']);
    }
}
