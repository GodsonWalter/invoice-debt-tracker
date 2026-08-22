<?php

namespace App\Exceptions;

use RuntimeException;

class SmsApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(string $message, public readonly array $response = [])
    {
        parent::__construct($message);
    }
}
