<?php

declare(strict_types=1);

namespace Quill\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Quill\Runtime\Runtime;
use Quill\Validation\Validator;
use Quill\Validation\DTO;
use Quill\Attributes\Required;
use Quill\Attributes\Email;
use Quill\Validation\ValidationException;

class StandardTestDto extends DTO {
    #[Required]
    public string $name;

    #[Email]
    public string $email;

    public int $age = 18;
}

class ValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
        if (!Runtime::boot()) {
            $libName = PHP_OS_FAMILY === 'Darwin' ? 'libquill.dylib' : 'libquill.so';
            $vendorBin = __DIR__ . '/../../../vendor/quillphp/quill-core/bin/';

            Runtime::init(
                soPath:     $vendorBin . $libName,
                headerPath: $vendorBin . 'quill.h',
            );
        }

        if (!Runtime::isAvailable()) {
            $this->markTestSkipped('Quill Core (libquill.so) required for tests.');
        }

        putenv('QUILL_RUNTIME=rust');
    }

    #[Test]
    public function it_validates_and_hydrates_dto_via_core(): void
    {
        $json = (string)json_encode([
            'name' => 'Quill User',
            'email' => 'quill@example.com',
            'age' => 30
        ]);

        /** @var StandardTestDto $dto */
        $dto = Validator::validate(StandardTestDto::class, $json);

        $this->assertInstanceOf(StandardTestDto::class, $dto);
        $this->assertSame('Quill User', $dto->name);
        $this->assertSame(30, $dto->age);
    }

    #[Test]
    public function it_throws_exception_on_invalid_data_via_core(): void
    {
        $json = (string)json_encode([
            'name' => '',
            'email' => 'not-an-email'
        ]);

        $this->expectException(ValidationException::class);
        Validator::validate(StandardTestDto::class, $json);
    }
}
