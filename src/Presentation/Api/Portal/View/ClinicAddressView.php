<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use App\Context\ClientPortal\Application\Port\ClinicAddress;

/** A linked clinic's letterhead address, shaped like a directory address. */
final readonly class ClinicAddressView
{
    public function __construct(
        /** Street lines 1 and 2 of the letterhead, joined by a comma. */
        public string $line,
        public ?string $postalCode,
        public string $city,
    ) {
    }

    public static function of(?ClinicAddress $address): ?self
    {
        return null === $address ? null : new self($address->line, $address->postalCode, $address->city);
    }
}
