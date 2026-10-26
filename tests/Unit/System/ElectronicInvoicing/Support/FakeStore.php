<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\System\ElectronicInvoicing\Application\Port\DocumentStoreInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\DocumentReference;

final class FakeStore implements DocumentStoreInterface
{
    /** @var array<string, string> */
    public array $stored = [];

    public function store(string $trackingId, string $sha256, string $content): DocumentReference
    {
        $this->stored[$sha256] = $content;

        return DocumentReference::of($trackingId . '.xml', $sha256);
    }

    public function load(DocumentReference $reference): string
    {
        return $this->stored[$reference->sha256()];
    }
}
