<?php

namespace App\Exceptions;

use Exception;

class VendorApiException extends Exception
{
    public static function notConfigured(string $vendor): self
    {
        return new self("{$vendor} API credentials are not configured. Add them to .env first.");
    }
}
