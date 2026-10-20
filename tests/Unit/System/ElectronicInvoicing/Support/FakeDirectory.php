<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\System\ElectronicInvoicing\Domain\Port\DirectoryEntry;
use App\System\ElectronicInvoicing\Domain\Port\DirectoryLookupInterface;

final class FakeDirectory implements DirectoryLookupInterface
{
    public function __construct(private readonly ?DirectoryEntry $entry)
    {
    }

    public function lookup(string $siren): DirectoryEntry
    {
        return $this->entry ?? throw ExternalServiceUnavailable::unreachable('directory');
    }
}
