<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\Exception;

final class TriageDeescalationNotAllowedException extends \DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(\sprintf(
            'Triage level change from "%s" to "%s" is not allowed.',
            $from,
            $to,
        ));
    }
}
