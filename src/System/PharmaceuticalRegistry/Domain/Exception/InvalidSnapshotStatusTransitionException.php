<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Exception;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportStatus;

final class InvalidSnapshotStatusTransitionException extends \DomainException
{
    public function __construct(ImportStatus $from, ImportStatus $to)
    {
        parent::__construct(\sprintf(
            'Invalid snapshot status transition from "%s" to "%s".',
            $from->value,
            $to->value,
        ));
    }
}
