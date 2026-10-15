<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\PeekNextDocumentNumbers;

use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PeekNextDocumentNumbersHandler
{
    public function __construct(private InvoiceNumberGeneratorInterface $numbers)
    {
    }

    public function __invoke(PeekNextDocumentNumbers $query): NextDocumentNumbers
    {
        $clinicId = ClinicId::fromString($query->clinicId);

        return new NextDocumentNumbers(
            invoice: $this->numbers->peekNext($clinicId, $query->year)->toString(),
            creditNote: $this->numbers->peekNextCreditNote($clinicId, $query->year)->toString(),
        );
    }
}
