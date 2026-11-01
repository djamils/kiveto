<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command;

use App\Context\ClientPortal\Application\Command\DeletePortalAccount\AccountErasureStepInterface;
use App\Context\ClientPortal\Application\Command\DeletePortalAccount\DeletePortalAccount;
use App\Context\ClientPortal\Application\Command\DeletePortalAccount\DeletePortalAccountHandler;
use App\Context\ClientPortal\Application\Command\DeleteUserDataExports\DeleteUserDataExports;
use App\Context\ClientPortal\Application\Port\DeletionCodeCheck;
use App\Context\ClientPortal\Application\Port\ErasedOwner;
use App\Context\ClientPortal\Application\Port\PortalAccountErasureInterface;
use App\Context\ClientPortal\Application\Port\PortalRecipientMaskerInterface;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Shared\Domain\ValueObject\PostalAddress;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalWorld as W;
use PHPUnit\Framework\TestCase;

/**
 * The erasure of a deleted account (S5g, AC 211): every step finds nothing
 * left to do once it ran, so a job interrupted halfway completes on retry
 * without duplicate effect.
 */
final class DeletePortalAccountHandlerTest extends TestCase
{
    private const string USER  = '01960000-0000-7000-8000-0000000007a1';
    private const string OTHER = '+33698765432';

    private W $world;

    /** @var \ArrayObject<int, string> */
    private \ArrayObject $calls;

    private AnsweringCommandBus $bus;

    private ErasedOwner $owner;

    /** @var \ArrayObject<string, int> */
    private \ArrayObject $maskFailures;

    protected function setUp(): void
    {
        $this->world        = new W();
        $this->owner        = new ErasedOwner(W::PHONE, 'sophie@example.com');
        $this->calls        = new \ArrayObject();
        $this->maskFailures = new \ArrayObject(['left' => 0]);
        $this->bus          = new AnsweringCommandBus(static fn (): null => null);
    }

    public function testAJobInterruptedAfterTheLinksCompletesOnRetry(): void
    {
        [, $pending] = $this->world->invite();
        $this->world->fakes->withClient(W::CLINIC2, W::CLIENT2, mobile: self::OTHER);
        [, $otherPhone] = $this->world->invite(W::CLINIC2, W::CLIENT2);
        $this->link(W::CLINIC, W::CLIENT);
        $this->link(W::CLINIC2, W::CLIENT2);
        $this->world->profiles->save(OwnerProfile::createFromClient(self::USER, 'Sophie', 'Martin', PostalAddress::create('12 rue des Lilas', 'Lyon', 'fr', null, '69003'), W::TERMS, $this->world->clock->now()));
        $this->world->fakes->events = [];
        $this->maskFailures['left'] = 1;
        $handler                    = $this->handler();

        try {
            $handler(new DeletePortalAccount(self::USER));
            self::fail('the masking failed');
        } catch (\RuntimeException) {
        }

        self::assertSame([ClientLinkStatus::REVOKED, ClientLinkStatus::REVOKED], $this->linkStatuses(), 'interrupted after the links');
        self::assertSame('Sophie', $this->world->profiles->findByUserId(self::USER)?->firstName(), 'the profile not yet');

        $handler(new DeletePortalAccount(self::USER));

        self::assertSame([ClientLinkStatus::REVOKED, ClientLinkStatus::REVOKED], $this->linkStatuses());
        self::assertSame(['client_portal.client-link.revoked.v1', 'client_portal.client-link.revoked.v1'], $this->world->fakes->eventNames(), 'each link revoked once');
        self::assertSame(PortalInvitationStatus::REVOKED, $this->world->invitation($pending)->status());
        self::assertSame(PortalInvitationStatus::PENDING, $this->world->invitation($otherPhone)->status(), 'another phone keeps its invitation');

        $profile = $this->world->profiles->findByUserId(self::USER);
        self::assertSame(['Compte', 'supprimé', null, W::TERMS], [$profile?->firstName(), $profile?->lastName(), $profile?->address(), $profile?->termsVersion()]);

        self::assertSame([
            'purge', 'step', 'mask:' . W::PHONE . ':sophie@example.com',
            'purge', 'step', 'mask:' . W::PHONE . ':sophie@example.com', 'anonymise',
        ], $this->calls->getArrayCopy());
        self::assertSame([[DeleteUserDataExports::class, self::USER], [DeleteUserDataExports::class, self::USER]], $this->dispatched());
    }

    public function testACompletedErasureRetriedTouchesNoPhoneAgain(): void
    {
        $this->owner = new ErasedOwner(null, null);
        $this->link(W::CLINIC, W::CLIENT);

        $this->handler()(new DeletePortalAccount(self::USER));

        self::assertSame(['purge', 'step', 'anonymise'], $this->calls->getArrayCopy(), 'no invitation lookup, no masking');
        self::assertSame(['phone' => 0], ['phone' => \count(array_filter($this->world->invitations->locks, static fn (string $l): bool => 'phone' === $l))]);
        self::assertSame([ClientLinkStatus::REVOKED], $this->linkStatuses(), 'links are by user id');
        self::assertSame([[DeleteUserDataExports::class, self::USER]], $this->dispatched());
    }

    private function handler(): DeletePortalAccountHandler
    {
        $accounts = new class($this->calls, $this->owner) implements PortalAccountErasureInterface {
            /** @param \ArrayObject<int, string> $calls */
            public function __construct(private readonly \ArrayObject $calls, private readonly ErasedOwner $owner)
            {
            }

            public function verifyDeletionCode(string $userId, string $challengeId, string $code): DeletionCodeCheck
            {
                throw new \LogicException('Not used.');
            }

            public function disable(string $userId): void
            {
                throw new \LogicException('Not used.');
            }

            public function purgeTraces(string $userId): ErasedOwner
            {
                $this->calls->append('purge');

                return $this->owner;
            }

            public function anonymise(string $userId): void
            {
                $this->calls->append('anonymise');
            }
        };

        $masker = new class($this->calls, $this->maskFailures) implements PortalRecipientMaskerInterface {
            /**
             * @param \ArrayObject<int, string> $calls
             * @param \ArrayObject<string, int> $failures
             */
            public function __construct(private readonly \ArrayObject $calls, private readonly \ArrayObject $failures)
            {
            }

            public function mask(string $phone, ?string $email): void
            {
                $this->calls->append('mask:' . $phone . ':' . $email);

                if ($this->failures['left'] > 0) {
                    $this->failures->offsetSet('left', $this->failures['left'] - 1);

                    throw new \RuntimeException('Notification unavailable.');
                }
            }
        };

        $step = new class($this->calls) implements AccountErasureStepInterface {
            /** @param \ArrayObject<int, string> $calls */
            public function __construct(private readonly \ArrayObject $calls)
            {
            }

            public function erase(string $userId, ErasedOwner $owner): void
            {
                $this->calls->append('step');
            }
        };

        return new DeletePortalAccountHandler(
            $accounts,
            $this->world->links,
            $this->world->invitations,
            $this->world->profiles,
            $masker,
            $this->bus,
            $this->world->publisher(),
            $this->world->clock,
            [$step],
        );
    }

    private function link(string $clinicId, string $clientId): void
    {
        $link = ClientLink::confirm(ClientLinkId::fromString(\sprintf('01960000-0000-7000-8000-%012d', \count($this->world->links->links) + 1)), self::USER, $clinicId, $clientId, $this->world->clock->now());
        self::assertCount(1, $link->pullDomainEvents(), 'the confirmation, published at acceptance');
        $this->world->links->save($link);
    }

    /**
     * @return list<array{class-string, string}>
     */
    private function dispatched(): array
    {
        return array_map(static function (object $command): array {
            \assert($command instanceof DeleteUserDataExports);

            return [$command::class, $command->userId];
        }, $this->bus->commands);
    }

    /**
     * @return list<ClientLinkStatus>
     */
    private function linkStatuses(): array
    {
        return array_values(array_map(static fn (ClientLink $l): ClientLinkStatus => $l->status(), $this->world->links->links));
    }
}
