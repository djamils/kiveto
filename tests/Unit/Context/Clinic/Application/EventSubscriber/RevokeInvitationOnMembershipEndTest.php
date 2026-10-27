<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\EventSubscriber;

use App\Context\Clinic\Application\EventSubscriber\RevokeInvitationOnMembershipEnd;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDisabled;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipValidityChanged;
use App\Context\Clinic\Infrastructure\Adapter\IdentityAccess\CommandBusInvitationRevoker;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\RevokeInvitation\RevokeInvitation;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\TestCase;

/**
 * A3: a member whose access was disabled or ended cannot activate their
 * account through an old invitation of the clinic; one who arrives later can.
 */
final class RevokeInvitationOnMembershipEndTest extends TestCase
{
    private const string CLINIC = '018f1b1e-1234-7890-abcd-0000000000a1';
    private const string USER   = '018f1b1e-1234-7890-abcd-0000000000b1';

    /** @var list<object> */
    private array $dispatched = [];

    public function testADisabledMembershipRevokesTheClinicsInvitation(): void
    {
        $this->subscriber()->onDisabled(new ClinicMembershipDisabled('m', self::CLINIC, self::USER));

        self::assertSame([(array) new RevokeInvitation(self::USER, self::CLINIC)], $this->revoked());
    }

    public function testAnEndedMembershipRevokesItAndALaterOrOpenEndedOneDoesNot(): void
    {
        $subscriber = $this->subscriber();

        $subscriber->onValidityChanged($this->window(null));
        $subscriber->onValidityChanged($this->window('2026-10-28T00:00:00+00:00'));
        self::assertSame([], $this->dispatched, 'still in force, or ending later');

        $subscriber->onValidityChanged($this->window('2026-10-27T09:59:59+00:00'));
        self::assertSame([(array) new RevokeInvitation(self::USER, self::CLINIC)], $this->revoked());
    }

    /**
     * @return list<array<mixed>>
     */
    private function revoked(): array
    {
        return array_map(static fn (object $command): array => (array) $command, $this->dispatched);
    }

    private function window(?string $validUntil): ClinicMembershipValidityChanged
    {
        return new ClinicMembershipValidityChanged('m', self::CLINIC, self::USER, '2026-01-01T00:00:00+00:00', $validUntil);
    }

    private function subscriber(): RevokeInvitationOnMembershipEnd
    {
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $command): int {
            $this->dispatched[] = $command;

            return 1;
        });

        return new RevokeInvitationOnMembershipEnd(new CommandBusInvitationRevoker($bus), new FrozenClock(new \DateTimeImmutable('2026-10-27 10:00:00', new \DateTimeZone('UTC'))));
    }
}
