<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCollectedTakings;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCollectedTakingsHandler
{
    public function __construct(private InvoiceReadRepositoryInterface $invoices)
    {
    }

    public function __invoke(GetCollectedTakings $query): int
    {
        return $this->invoices->collectedBetween(ClinicId::fromString($query->clinicId), $query->from, $query->to);
    }
}
