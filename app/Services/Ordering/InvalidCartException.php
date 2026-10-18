<?php

namespace App\Services\Ordering;

use RuntimeException;

class InvalidCartException extends RuntimeException
{
    /** @param  list<string>  $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors[0] ?? 'Keranjang tidak valid.');
    }
}
