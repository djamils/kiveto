<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command;

use App\Context\ClientPortal\Application\Command\SetPortalConsent\SetPortalConsent;
use App\Context\ClientPortal\Application\Command\SetPortalConsent\SetPortalConsentHandler;
use App\Context\ClientPortal\Application\Port\ClientConsentRecord;
use App\Context\ClientPortal\Application\Service\PortalConsentFiles;
use App\Context\ClientPortal\Domain\Exception\ClinicNotLinkedException;
use App\Context\ClientPortal\Domain\Exception\ConsentConflictException;
use App\Context\ClientPortal\Domain\Exception\InvalidConsentChoiceException;
use App\Context\ClientPortal\Domain\Exception\OwnerProfileNotFoundException;
use App\Context\ClientPortal\Domain\Exception\UnknownConsentKindException;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryClientConsents;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryClientLinks;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryOwnerProfiles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/** Task 33: the owner's change lands on each active linked file of the clinic (AC 92, AC 205). */
final class SetPortalConsentHandlerTest extends TestCase
{
    private const string USER   = 'user-1';
    private const string CLINIC = 'clinic-1';

    private InMemoryClientLinks $links;

    private InMemoryClientConsents $consents;

    private InMemoryOwnerProfiles $profiles;

    private SetPortalConsentHandler $handler;

    protected function setUp(): void
    {
        $this->links    = new InMemoryClientLinks();
        $this->consents = new InMemoryClientConsents();
        $this->profiles = new InMemoryOwnerProfiles();
        $this->profiles->save(OwnerProfile::createFromClient(self::USER, 'Sophie', 'Martin', null, 'v1', new \DateTimeImmutable('2026-10-01')));
        $this->handler = new SetPortalConsentHandler(new PortalConsentFiles($this->links, $this->consents), $this->consents, $this->profiles);

        $this->file('client-active');
        $this->file('client-archived', archived: true);
        $this->file('client-other-clinic', clinicId: 'clinic-2');
    }

    public function testTheActiveFileIsWrittenAndTheArchivedOneSkipped(): void
    {
        ($this->handler)(new SetPortalConsent(self::USER, self::CLINIC, 'marketing', ['email', 'sms', 'email']));

        self::assertSame([[self::CLINIC, 'client-active', 'marketing', ['sms', 'email'], self::USER, 'Sophie Martin']], $this->consents->writes);
    }

    public function testEveryActiveFileOfTheClinicIsWritten(): void
    {
        $this->file('client-second');

        ($this->handler)(new SetPortalConsent(self::USER, self::CLINIC, 'marketing', []));

        self::assertSame(['client-active', 'client-second'], array_column($this->consents->writes, 1));
        self::assertSame([[], []], array_column($this->consents->writes, 3));
    }

    public function testAKindSentAnywayKeepsAChannel(): void
    {
        $this->expectExceptionObject(InvalidConsentChoiceException::channelRequired());

        try {
            ($this->handler)(new SetPortalConsent(self::USER, self::CLINIC, 'billing', []));
        } finally {
            self::assertSame([], $this->consents->writes);
        }
    }

    public function testAChannelOtherThanSmsOrEmailIsRefused(): void
    {
        $this->expectExceptionObject(InvalidConsentChoiceException::unknownChannel('phone'));

        ($this->handler)(new SetPortalConsent(self::USER, self::CLINIC, 'marketing', ['phone']));
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectException(UnknownConsentKindException::class);

        ($this->handler)(new SetPortalConsent(self::USER, self::CLINIC, 'newsletter', ['sms']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNoActiveFileIsNotFoundCases(): iterable
    {
        yield 'only an archived file' => ['clinic-archived-only'];
        yield 'no link' => ['clinic-3'];
    }

    #[DataProvider('provideNoActiveFileIsNotFoundCases')]
    public function testNoActiveFileIsNotFound(string $clinicId): void
    {
        $this->file('client-x', archived: true, clinicId: 'clinic-archived-only');
        $this->expectException(ClinicNotLinkedException::class);

        ($this->handler)(new SetPortalConsent(self::USER, $clinicId, 'marketing', ['sms']));
    }

    public function testAnAccountWithoutProfileIsNotFound(): void
    {
        $this->profiles->profiles = [];
        $this->expectException(OwnerProfileNotFoundException::class);

        ($this->handler)(new SetPortalConsent(self::USER, self::CLINIC, 'marketing', ['sms']));
    }

    public function testAConflictOfTheWriterGoesThrough(): void
    {
        $this->consents->failure = new ConsentConflictException();
        $this->expectException(ConsentConflictException::class);

        ($this->handler)(new SetPortalConsent(self::USER, self::CLINIC, 'marketing', ['sms']));
    }

    private function file(string $clientId, bool $archived = false, string $clinicId = self::CLINIC): void
    {
        $this->links->save(ClientLink::confirm(ClientLinkId::fromString(Uuid::v7()->toRfc4122()), self::USER, $clinicId, $clientId, new \DateTimeImmutable('2026-10-01')));
        $this->consents->records[$clientId] = new ClientConsentRecord($clientId, null, $archived, []);
    }
}
