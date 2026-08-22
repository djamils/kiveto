<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\FindInvoiceForAdmission;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FindInvoiceForAdmissionHandler
{
    public function __construct(private InvoiceReadRepositoryInterface $invoices)
    {
    }

    public function __invoke(FindInvoiceForAdmission $query): ?AdmissionInvoiceRow
    {
        return $this->invoices->forAdmission(
            ClinicId::fromString($query->clinicId),
            AdmissionId::fromString($query->admissionId),
        );
    }
}
