<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain\Event;

use App\Shared\Domain\Event\IntegrationEventInterface;
use App\System\PharmaceuticalRegistry\Domain\Event\ControlledSubstanceClassificationChanged;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationStatusChanged;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationSuspended;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationUpdated;
use App\System\PharmaceuticalRegistry\Domain\Event\MarketingAuthorizationWithdrawn;
use App\System\PharmaceuticalRegistry\Domain\Event\PrescriptionRequirementChanged;
use App\System\PharmaceuticalRegistry\Domain\Event\PresentationAdded;
use App\System\PharmaceuticalRegistry\Domain\Event\PresentationGtinChanged;
use App\System\PharmaceuticalRegistry\Domain\Event\PresentationRemoved;
use App\System\PharmaceuticalRegistry\Domain\Event\WithdrawalPeriodChanged;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IntegrationEventComplianceTest extends TestCase
{
    #[DataProvider('provideImplementsIntegrationEventInterfaceCases')]
    public function testImplementsIntegrationEventInterface(string $className): void
    {
        self::assertTrue(
            is_a($className, IntegrationEventInterface::class, true),
            "$className must implement IntegrationEventInterface",
        );
    }

    /** @return array<array{string}> */
    public static function provideImplementsIntegrationEventInterfaceCases(): iterable
    {
        return [
            [MarketingAuthorizationUpdated::class],
            [MarketingAuthorizationWithdrawn::class],
            [MarketingAuthorizationSuspended::class],
            [MarketingAuthorizationStatusChanged::class],
            [PresentationAdded::class],
            [PresentationRemoved::class],
            [PresentationGtinChanged::class],
            [WithdrawalPeriodChanged::class],
            [PrescriptionRequirementChanged::class],
            [ControlledSubstanceClassificationChanged::class],
        ];
    }
}
