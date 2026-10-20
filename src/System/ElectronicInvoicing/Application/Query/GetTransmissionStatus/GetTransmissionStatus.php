<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Where the document with this tracking id stands; null when it was never requested.
 */
final readonly class GetTransmissionStatus implements QueryInterface
{
    public function __construct(public string $documentId)
    {
    }
}
