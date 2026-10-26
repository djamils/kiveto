<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The owner block of the consultation report: the primary-or-first phone and
 * e-mail, the postal address on one line.
 */
final readonly class ReportOwner
{
    public function __construct(
        public string $clientId,
        public string $fullName,
        public ?string $phone,
        public ?string $email,
        public ?string $address,
    ) {
    }
}
