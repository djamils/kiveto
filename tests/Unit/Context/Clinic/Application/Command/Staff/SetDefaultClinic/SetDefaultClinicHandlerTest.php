<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\SetDefaultClinic;

use App\Context\Clinic\Application\Command\Staff\SetDefaultClinic\SetDefaultClinic;
use App\Context\Clinic\Application\Command\Staff\SetDefaultClinic\SetDefaultClinicHandler;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDefaultChanged;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;

final class SetDefaultClinicHandlerTest extends TestCase
{
    private const string CLINIC_ID_A     = '01912345-6789-7abc-8def-000000000001';
    private const string CLINIC_ID_B     = '01912345-6789-7abc-8def-000000000002';
    private const string USER_ID         = '01912345-6789-7abc-8def-000000000010';
    private const string MEMBERSHIP_ID_A = '01912345-6789-7abc-8def-000000000101';
    private const string MEMBERSHIP_ID_B = '01912345-6789-7abc-8def-000000000102';

    public function testSetsDefaultOnFirstTimeSelection(): void
    {
        $target = $this->buildMembership(self::MEMBERSHIP_ID_A, self::CLINIC_ID_A, isDefault: false);

        $membershipRepo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('findByClinicAndUser')->willReturn($target);
        $membershipRepo->method('findDefaultForUser')->willReturn(null);
        $membershipRepo->expects(self::once())->method('saveAll')->with(self::isInstanceOf(ClinicMembership::class));

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')->with(
            [],
            self::callback(static function (object $event): bool {
                return $event instanceof ClinicMembershipDefaultChanged
                    && true === $event->payload()['isDefault'];
            }),
        );

        $handler = new SetDefaultClinicHandler($membershipRepo, new DomainEventPublisher($eventBus));

        $handler(new SetDefaultClinic(clinicId: self::CLINIC_ID_A, userId: self::USER_ID));
    }

    public function testChangesDefaultFromPreviousToNew(): void
    {
        $oldDefault = $this->buildMembership(self::MEMBERSHIP_ID_A, self::CLINIC_ID_A, isDefault: true);
        $target     = $this->buildMembership(self::MEMBERSHIP_ID_B, self::CLINIC_ID_B, isDefault: false);

        $membershipRepo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('findByClinicAndUser')->willReturn($target);
        $membershipRepo->method('findDefaultForUser')->willReturn($oldDefault);
        $membershipRepo->expects(self::once())->method('saveAll')->with(
            self::isInstanceOf(ClinicMembership::class),
            self::isInstanceOf(ClinicMembership::class),
        );

        $publishCalls = [];
        $eventBus     = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::exactly(2))->method('publish')->willReturnCallback(
            static function (array $stamps, object ...$events) use (&$publishCalls): void {
                foreach ($events as $event) {
                    $publishCalls[] = $event;
                }
            },
        );

        $handler = new SetDefaultClinicHandler($membershipRepo, new DomainEventPublisher($eventBus));

        $handler(new SetDefaultClinic(clinicId: self::CLINIC_ID_B, userId: self::USER_ID));

        self::assertCount(2, $publishCalls);

        self::assertInstanceOf(ClinicMembershipDefaultChanged::class, $publishCalls[0]);
        self::assertFalse($publishCalls[0]->payload()['isDefault']);

        self::assertInstanceOf(ClinicMembershipDefaultChanged::class, $publishCalls[1]);
        self::assertTrue($publishCalls[1]->payload()['isDefault']);
    }

    public function testIdempotentWhenSameClinicReselected(): void
    {
        $target = $this->buildMembership(self::MEMBERSHIP_ID_A, self::CLINIC_ID_A, isDefault: true);

        $membershipRepo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('findByClinicAndUser')->willReturn($target);
        $membershipRepo->method('findDefaultForUser')->willReturn($target);
        $membershipRepo->expects(self::once())->method('saveAll')->with(self::isInstanceOf(ClinicMembership::class));

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::never())->method('publish');

        $handler = new SetDefaultClinicHandler($membershipRepo, new DomainEventPublisher($eventBus));

        $handler(new SetDefaultClinic(clinicId: self::CLINIC_ID_A, userId: self::USER_ID));
    }

    public function testThrowsWhenMembershipNotFound(): void
    {
        $membershipRepo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('findByClinicAndUser')->willReturn(null);
        $membershipRepo->expects(self::never())->method('saveAll');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::never())->method('publish');

        $handler = new SetDefaultClinicHandler($membershipRepo, new DomainEventPublisher($eventBus));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No membership found');

        $handler(new SetDefaultClinic(clinicId: self::CLINIC_ID_A, userId: self::USER_ID));
    }

    private function buildMembership(string $id, string $clinicId, bool $isDefault): ClinicMembership
    {
        return ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString($id),
            clinicId: ClinicId::fromString($clinicId),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            isDefault: $isDefault,
        );
    }
}
