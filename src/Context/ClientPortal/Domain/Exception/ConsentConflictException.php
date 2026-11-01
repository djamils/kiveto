<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** A client file changed while its consent was written: read again, then retry (a 409). */
final class ConsentConflictException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('The consents changed concurrently.', 0, $previous);
    }
}
