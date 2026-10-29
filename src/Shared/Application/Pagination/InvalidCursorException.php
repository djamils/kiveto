<?php

declare(strict_types=1);

namespace App\Shared\Application\Pagination;

/**
 * Thrown when a client-supplied cursor cannot be decoded (truncated, edited or
 * forged). Presentation maps it to `400 cursor_invalid`.
 */
final class InvalidCursorException extends \InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('The pagination cursor is invalid.');
    }
}
