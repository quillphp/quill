<?php

declare(strict_types=1);

namespace Quill\Validation;

/**
 * ValidationException — thrown when DTO validation fails.
 * Handled automatically by App.php.
 */
class ValidationException extends \Exception
{
    /** @var array<string, array<string>> */
    private array $errors;

    /**
     * @param array<string, array<string>> $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct('Validation Failed');
        $this->errors = $errors;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
