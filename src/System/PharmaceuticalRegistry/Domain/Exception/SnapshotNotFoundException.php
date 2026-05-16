<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Exception;

final class SnapshotNotFoundException extends \DomainException
{
    public function __construct(string $snapshotId)
    {
        parent::__construct(\sprintf('Snapshot not found: "%s".', $snapshotId));
    }
}
