<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\TaxProfile\TaxProfileVerification;
use App\Context\Client\Domain\TaxProfile\VerificationRequestOutcome;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Siren;
use PHPUnit\Framework\TestCase;

/**
 * « Revérifier »: a new verification is requested unless one is still awaited.
 */
final class ClientTaxProfileVerificationRequestTest extends TestCase
{
    private const string NOW = '2026-10-19 10:00:00';

    public function testAVerifiableClientIsQueued(): void
    {
        $client = self::client(self::professional(), null);
        $now    = new \DateTimeImmutable(self::NOW);

        self::assertSame(VerificationRequestOutcome::QUEUED, $client->requestTaxProfileVerification($now));
        self::assertSame($now, $client->taxProfileVerification()->requestedAt());
        self::assertSame([], $client->pullDomainEvents());
    }

    public function testARequestLessThanADayOldIsStillPending(): void
    {
        $client = self::client(self::professional(), '2026-10-18 10:00:01');

        self::assertSame(VerificationRequestOutcome::ALREADY_PENDING, $client->requestTaxProfileVerification(new \DateTimeImmutable(self::NOW)));
        self::assertSame((new \DateTimeImmutable('2026-10-18 10:00:01'))->format(\DATE_ATOM), $client->taxProfileVerification()->requestedAt()?->format(\DATE_ATOM));
    }

    public function testARequestADayOldIsQueuedAgain(): void
    {
        $client = self::client(self::professional(), '2026-10-18 10:00:00');

        self::assertSame(VerificationRequestOutcome::QUEUED, $client->requestTaxProfileVerification(new \DateTimeImmutable(self::NOW)));
    }

    public function testAnArchivedClientIsNotQueued(): void
    {
        $client = self::client(self::professional(), null, ClientStatus::ARCHIVED);

        self::assertSame(VerificationRequestOutcome::ARCHIVED, $client->requestTaxProfileVerification(new \DateTimeImmutable(self::NOW)));
        self::assertNull($client->taxProfileVerification()->requestedAt());
    }

    public function testAnIndividualIsNotApplicable(): void
    {
        $client = self::client(ClientTaxProfile::individual('Refuge des Collines'), null);

        self::assertSame(VerificationRequestOutcome::NOT_APPLICABLE, $client->requestTaxProfileVerification(new \DateTimeImmutable(self::NOW)));
        self::assertNull($client->taxProfileVerification()->requestedAt());
    }

    private static function professional(): ClientTaxProfile
    {
        return ClientTaxProfile::frenchBusiness('Centre équestre du Lac', Siren::fromInput('812345676'), null);
    }

    private static function client(ClientTaxProfile $profile, ?string $requestedAt, ClientStatus $status = ClientStatus::ACTIVE): Client
    {
        return Client::reconstitute(
            id: ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            identity: new ClientIdentity('Paul', 'Lac'),
            status: $status,
            contactMethods: [ContactMethod::email(EmailAddress::fromString('paul@lac.fr'), ContactLabel::WORK, true)],
            createdAt: new \DateTimeImmutable('2026-10-01'),
            updatedAt: new \DateTimeImmutable('2026-10-01'),
            taxProfile: $profile,
            taxProfileVerification: TaxProfileVerification::unchecked(null === $requestedAt ? null : new \DateTimeImmutable($requestedAt)),
        );
    }
}
