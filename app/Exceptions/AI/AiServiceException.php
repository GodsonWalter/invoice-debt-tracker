<?php

namespace App\Exceptions\AI;

use Exception;

class AiServiceException extends Exception
{
    public static function timeout(): self
    {
        return new self('OpenAI API request timed out.');
    }

    public static function apiError(string $message): self
    {
        return new self('OpenAI API error: '.$message);
    }

    public static function noResponse(): self
    {
        return new self('OpenAI API returned no response.');
    }

    public static function invalidJson(string $message = ''): self
    {
        return new self('AI returned invalid JSON: '.$message);
    }
}
