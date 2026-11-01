<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command;

use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\CompletePortalRegistration;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\CompletePortalRegistrationHandler;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\RegistrationOutcome;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\RegistrationResult;
use App\Context\ClientPortal\Application\Port\PortalRegistration;
use App\Context\ClientPortal\Application\Port\PortalRegistrationResult;
use App\Context\ClientPortal\Domain\Exception\TermsOutdatedException;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\System\IdentityAccess\Domain\Exception\PortalAccountExistsException;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalFakes;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryOwnerProfiles;
use PHPUnit\Framework\TestCase;

/**
 * S7a (ClientPortal side), AC 72, 262, 263: terms first, then the account
 * (IdentityAccess), then the owner profile from the entered names.
 */
final class CompletePortalRegistrationHandlerTest extends TestCase
{
    private const string TERMS = '2026-10-29';

    private ClientPortalFakes $fakes;
    private InMemoryOwnerProfiles $profiles;

    protected function setUp(): void
    {
        $this->fakes    = new ClientPortalFakes();
        $this->profiles = new InMemoryOwnerProfiles();
    }

    public function testTheAccountAndItsProfileAreCreated(): void
    {
        $outcome = $this->complete(email: 'sophie@example.com');

        self::assertSame([RegistrationResult::REGISTERED, ClientPortalFakes::USER], [$outcome->result, $outcome->userId]);
        self::assertSame([['reg-token', 'sophie@example.com']], $this->fakes->registrations);
        $profile = $this->profiles->findByUserId(ClientPortalFakes::USER);
        self::assertNotNull($profile);
        self::assertSame(['Sophie', 'Martin', null, self::TERMS, '2026-11-02 09:00'], [
            $profile->firstName(), $profile->lastName(), $profile->address(), $profile->termsVersion(), $profile->termsAcceptedAt()->format('Y-m-d H:i'),
        ]);
    }

    /** AC 72: a replay answers the account and never overwrites its profile. */
    public function testAReplayWritesNothing(): void
    {
        $existing                                          = OwnerProfile::register(ClientPortalFakes::USER, 'Sophie', 'Martin', self::TERMS, new \DateTimeImmutable('2026-11-02 08:58'));
        $this->profiles->profiles[ClientPortalFakes::USER] = $existing;
        $this->answer(PortalRegistrationResult::REPLAYED, ClientPortalFakes::USER);

        $outcome = $this->complete(firstName: 'Autre');

        self::assertSame([RegistrationResult::REPLAYED, ClientPortalFakes::USER], [$outcome->result, $outcome->userId]);
        self::assertSame([0, 'Sophie'], [$this->profiles->saves, $existing->firstName()]);
    }

    public function testARefusedGrantOrADisabledAccountCreatesNoProfile(): void
    {
        foreach ([[PortalRegistrationResult::ONBOARDING_INVALID, RegistrationResult::ONBOARDING_INVALID], [PortalRegistrationResult::ACCOUNT_DISABLED, RegistrationResult::ACCOUNT_DISABLED]] as [$answer, $expected]) {
            $this->answer($answer, null);

            self::assertSame([$expected, null], [$this->complete()->result, $this->complete()->userId]);
        }

        self::assertSame([], $this->profiles->profiles);
    }

    /** AC 263: outdated terms are refused before IdentityAccess is asked anything. */
    public function testOutdatedTermsAreRefusedFirst(): void
    {
        try {
            $this->complete(termsVersion: '2025-01-01');
            self::fail('The terms are outdated.');
        } catch (TermsOutdatedException $exception) {
            self::assertSame(['2025-01-01', self::TERMS], [$exception->acceptedVersion, $exception->currentVersion]);
        }

        self::assertSame([[], []], [$this->fakes->registrations, $this->profiles->profiles]);
    }

    /** AC 262: an existing account aborts the registration; no profile is written. */
    public function testAnExistingAccountGoesThroughAndNoProfileIsWritten(): void
    {
        $this->fakes->registrationAnswer = static fn (string $token): PortalRegistration => throw new PortalAccountExistsException();

        try {
            $this->complete();
            self::fail('The phone already has an account.');
        } catch (PortalAccountExistsException) {
            self::assertSame([], $this->profiles->profiles);
        }
    }

    public function testARegistrationWithoutAccountIdIsALogicError(): void
    {
        $this->answer(PortalRegistrationResult::REGISTERED, null);

        $this->expectException(\LogicException::class);
        $this->complete();
    }

    private function answer(PortalRegistrationResult $result, ?string $userId): void
    {
        $this->fakes->registrationAnswer = static fn (string $token): PortalRegistration => new PortalRegistration($result, $userId);
    }

    private function complete(string $firstName = 'Sophie', ?string $email = null, string $termsVersion = self::TERMS): RegistrationOutcome
    {
        $handler = new CompletePortalRegistrationHandler($this->fakes, $this->profiles, new FrozenClock(new \DateTimeImmutable('2026-11-02 09:00:00')), self::TERMS);

        return $handler(new CompletePortalRegistration('reg-token', $firstName, 'Martin', $email, $termsVersion));
    }
}
