<?php

namespace App\Core;

/**
 * HTTP exception (404/403/422/500...) — converted to JSON or HTML by App.
 */
class HttpException extends \Exception
{
    /** @var array<string,string[]> */
    public array $errors = [];

    public function __construct(int $status, string $message = '', array $errors = [])
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
        $this->errors = $errors;
    }

    public function status(): int
    {
        return $this->getCode();
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Unauthenticated.',
            403 => 'This action is unauthorized.',
            404 => 'Not found',
            405 => 'Method not allowed',
            419 => 'CSRF token mismatch',
            422 => 'The given data was invalid.',
            default => 'Server error',
        };
    }
}
