<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Who the clinic is as the seller of its invoices: the identity an invoice must
 * carry for the company that issues it.
 */
final readonly class GetClinicSellerIdentity implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
