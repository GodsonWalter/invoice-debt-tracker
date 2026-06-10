<?php

namespace App\Exceptions\AI;

use Exception;

class InvalidQueryException extends Exception
{
    public static function invalidJson(string $message = ''): self
    {
        return new self('AI returned invalid JSON: '.$message);
    }

    public static function invalidEntity(string $entity): self
    {
        return new self("Entity '{$entity}' is not supported. Only 'invoice' is allowed.");
    }

    public static function invalidField(string $field): self
    {
        return new self("Field '{$field}' is not allowed for querying.");
    }

    public static function invalidOperator(string $operator): self
    {
        return new self("Operator '{$operator}' is not allowed.");
    }

    public static function invalidStatus(string $status): self
    {
        return new self("Status '{$status}' is not a valid invoice status.");
    }

    public static function invalidValue(string $field, mixed $value): self
    {
        return new self("Invalid value '{$value}' for field '{$field}'.");
    }
}
