<?php

declare(strict_types=1);

namespace App\Tests\Unit\ClinicalCare\Domain\ValueObject;

use App\ClinicalCare\Domain\ValueObject\ConsultationStatus;
use PHPUnit\Framework\TestCase;

final class ConsultationStatusTest extends TestCase
{
    public function testIsOpenOnlyReturnsTrueForOpen(): void
    {
        self::assertTrue(ConsultationStatus::OPEN->isOpen());
        self::assertFalse(ConsultationStatus::CLOSED->isOpen());
    }

    public function testIsClosedOnlyReturnsTrueForClosed(): void
    {
        self::assertTrue(ConsultationStatus::CLOSED->isClosed());
        self::assertFalse(ConsultationStatus::OPEN->isClosed());
    }

    public function testCanTransitionFromOpenToClosed(): void
    {
        self::assertTrue(ConsultationStatus::OPEN->canTransitionTo(ConsultationStatus::CLOSED));
        self::assertFalse(ConsultationStatus::OPEN->canTransitionTo(ConsultationStatus::OPEN));
    }

    public function testCannotTransitionFromClosed(): void
    {
        self::assertFalse(ConsultationStatus::CLOSED->canTransitionTo(ConsultationStatus::OPEN));
        self::assertFalse(ConsultationStatus::CLOSED->canTransitionTo(ConsultationStatus::CLOSED));
    }
}
