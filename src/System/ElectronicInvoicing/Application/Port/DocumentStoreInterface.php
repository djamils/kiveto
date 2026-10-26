<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Port;

use App\System\ElectronicInvoicing\Domain\Transmission\DocumentReference;

interface DocumentStoreInterface
{
    /**
     * Stores the rendered file of a transmission; storing the same content
     * twice is harmless.
     */
    public function store(string $trackingId, string $sha256, string $content): DocumentReference;

    public function load(DocumentReference $reference): string;
}
