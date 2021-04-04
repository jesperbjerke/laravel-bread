<?php

namespace Bjerke\Bread\Exceptions;

use Exception;

class InvalidFieldDefinition extends Exception
{
    public function __construct(
        string $fieldName,
        string $message
    ) {
        parent::__construct("Field defintion for $fieldName is invalid: $message");
    }
}
