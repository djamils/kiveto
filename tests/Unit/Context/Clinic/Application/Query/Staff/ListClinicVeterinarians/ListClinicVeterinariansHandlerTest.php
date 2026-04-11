<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians;

use App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinariansHandler;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListClinicVeterinariansHandlerTest extends TestCase
{
    public function testDelegatesToReadRepositoryWithClinicId(): void
    {
        $clinicIdStr  = '12345678-9abc-def0-1234-56789abcdef0';
        $expectedItem = new ClinicVeterinarianItem(
            userId: '01912345-6789-7abc-8def-0000000000a1',
            role: 'VETERINARY',
            engagement: 'EMPLOYEE',
        );

        $readRepository = $this->createMock(ClinicMembershipReadRepositoryInterface::class);
        $readRepository
            ->expects(self::once())
            ->method('findVeterinariansForClinic')
            ->with(self::callback(
                static fn (ClinicId $id): bool => $id->toString() === $clinicIdStr,
            ))
            ->willReturn([$expectedItem])
        ;

        $handler = new ListClinicVeterinariansHandler($readRepository);

        $result = $handler(new ListClinicVeterinarians($clinicIdStr));

        self::assertSame([$expectedItem], $result);
    }
}
