<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\Exception;

final class AdmissionReopenNotAllowedException extends \DomainException
{
    public function __construct(string $admissionId, string $closureReason)
    {
        parent::__construct(\sprintf(
            'Admission "%s" cannot be reopened: closure reason "%s" does not allow reopen.',
            $admissionId,
            $closureReason,
        ));
    }
}
