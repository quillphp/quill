<?php

declare(strict_types=1);

namespace Quill\Tests;

use PHPUnit\Framework\TestCase;
use Quill\Validator;
use Quill\DTO;
use Quill\ValidationException;
use Quill\Attributes\{Email, MinLength};

class MockUserDTO extends DTO
{
    public string $name;
    public int $age = 25;
}

class MockEmailDTO extends DTO
{
    #[Email]
    public string $contact = '';
}

class MockMinDTO extends DTO
{
    #[MinLength(5)]
    public string $tag = '';
}

class MockAdvancedDTO extends DTO
{
    #[\Quill\Attributes\Regex('/^K-\d+$/')]
    public string $serial;

    #[\Quill\Attributes\Numeric]
    public float $price;

    #[\Quill\Attributes\Boolean]
    public bool $active;

    public ?string $description;
}

class ValidatorTest extends TestCase
{
    public function testValidDtoHydration(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $dto = Validator::validate(MockUserDTO::class, $data);

        $this->assertInstanceOf(MockUserDTO::class, $dto);
        $this->assertEquals('John', $dto->name);
        $this->assertEquals(30, $dto->age);
    }

    public function testDefaultValueUsage(): void
    {
        $data = ['name' => 'Jane'];
        $dto = Validator::validate(MockUserDTO::class, $data);

        $this->assertEquals(25, $dto->age);
    }

    public function testValidationFailsOnMissingRequiredField(): void
    {
        $this->expectException(ValidationException::class);
        Validator::validate(MockUserDTO::class, ['age' => 45]);
    }

    public function testValidationExceptionContainsFieldErrors(): void
    {
        try {
            Validator::validate(MockUserDTO::class, ['age' => 45]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->getErrors());
        }
    }

    public function testEmailAttributeRejectsInvalidEmail(): void
    {
        $this->expectException(ValidationException::class);
        Validator::validate(MockEmailDTO::class, ['contact' => 'not-an-email']);
    }

    public function testMinLengthAttributeRejectsShortString(): void
    {
        $this->expectException(ValidationException::class);
        Validator::validate(MockMinDTO::class, ['tag' => 'ab']);
    }

    public function testAdvancedRulesPass(): void
    {
        $data = [
            'serial' => 'K-123',
            'price' => '19.99',
            'active' => 'true',
            'description' => null
        ];
        $dto = Validator::validate(MockAdvancedDTO::class, $data);
        $this->assertEquals('K-123', $dto->serial);
        $this->assertEquals(19.99, $dto->price);
        $this->assertTrue($dto->active);
        $this->assertNull($dto->description);
    }

    public function testRegexRejectsInvalidPattern(): void
    {
        $this->expectException(ValidationException::class);
        Validator::validate(MockAdvancedDTO::class, ['serial' => 'ABC-123']);
    }

    public function testNumericRejectsNonNumeric(): void
    {
        $this->expectException(ValidationException::class);
        Validator::validate(MockAdvancedDTO::class, ['price' => 'free']);
    }

    public function testNullableAllowsMissingField(): void
    {
        $data = [
            'serial' => 'K-1',
            'price' => 10,
            'active' => false
        ];
        $dto = Validator::validate(MockAdvancedDTO::class, $data);
        $this->assertObjectHasProperty('description', $dto);
        // Note: description property exists but is not set if missing and no default?
        // Actually, if missing and nullable, it should be null.
    }
}
