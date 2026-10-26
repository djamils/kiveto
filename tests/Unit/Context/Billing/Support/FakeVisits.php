<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Application\Port\VisitDirectoryInterface;
use App\Context\Billing\Application\Port\VisitSummary;

final class FakeVisits implements VisitDirectoryInterface
{
    /** @var array<string, VisitSummary> */
    public array $visits = [];

    public function __construct(?string $animal = 'Minou')
    {
        $this->visits[Invoices::ADMISSION] = new VisitSummary($animal, new \DateTimeImmutable('2026-10-16 07:45:00', new \DateTimeZone('UTC')));
    }

    public function visitOf(string $clinicId, string $admissionId): ?VisitSummary
    {
        return $this->visits[$admissionId] ?? null;
    }
}
