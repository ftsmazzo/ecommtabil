<?php

namespace App\Exceptions;

use Exception;

abstract class DomainException extends Exception
{
    protected ?string $redirectRoute = null;

    public function redirectRoute(): ?string
    {
        return $this->redirectRoute;
    }
}
