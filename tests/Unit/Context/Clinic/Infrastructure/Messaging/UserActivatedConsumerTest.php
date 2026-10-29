<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Messaging;

use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Infrastructure\Messaging\Consumer\UserActivatedConsumer;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Domain\Event\UserActivatedIntegrationEvent;
use PHPUnit\Framework\TestCase;

final class UserActivatedConsumerTest extends TestCase
{
    public function testTheClinicsWhereTheUserIsAVeterinarianAreRecounted(): void
    {
        $memberships = $this->createStub(ClinicMembershipReadRepositoryInterface::class);
        $memberships->method('findAccessibleClinicsForUser')->willReturn([
            self::clinic('c1', ClinicMemberRole::VETERINARY),
            self::clinic('c2', ClinicMemberRole::RECEPTIONIST),
            self::clinic('c3', ClinicMemberRole::VETERINARY),
        ]);

        $dispatched = [];
        $bus        = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $command) use (&$dispatched): null {
            $dispatched[] = $command;

            return null;
        });

        new UserActivatedConsumer($memberships, $bus)(new UserActivatedIntegrationEvent('018f1b1e-1234-7890-abcd-0123456789ab', '2026-10-26T10:00:00+00:00'));

        self::assertContainsOnlyInstancesOf(RecordVeterinarianDailyCounts::class, $dispatched);
        self::assertSame(['c1', 'c3'], array_map(static fn (RecordVeterinarianDailyCounts $c): ?string => $c->clinicId, $dispatched));
    }

    private static function clinic(string $id, ClinicMemberRole $role): AccessibleClinic
    {
        return new AccessibleClinic($id, 'Clinique', 'clinique', 'active', $role, ClinicMembershipEngagement::EMPLOYEE, new \DateTimeImmutable('2026-01-01'), null, false);
    }
}
