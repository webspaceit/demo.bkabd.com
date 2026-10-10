<?php

namespace App\Core;

/**
 * Validation failure — carried to App's exception handler (422 for Ajax,
 * redirect-back-with-errors for plain form posts).
 *
 * Own file (PSR-4 autoloadable) so it can be thrown before the Validator
 * itself has been loaded — e.g. the hardened upload helper in
 * StudentController throws this directly.
 */
class ValidationException extends \Exception
{
    /** @var array<string,string[]> */
    public array $errors;

    /** @param array<string,string[]> $errors */
    public function __construct(array $errors)
    {
        parent::__construct('The given data was invalid.');
        $this->errors = $errors;
    }
}