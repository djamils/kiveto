<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\Consultation;

use App\Context\Animal\Domain\ValueObject\OwnershipRole;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\ClientLinkEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Consultation\Factory\ConsultationEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * « Partager sur le Portal » in the documents panel of a consultation
 * (Task 10 — AC 10, 104): the panel state, the share of a closed
 * consultation, the refusals and the owner without Portal account.
 */
final class ShareConsultationReportOnPortalTest extends WebTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000f10c1';
    private const string HOST   = 'http://clinic.kiveto.local';

    private const string CLOSED     = '01960000-0000-7000-8000-0000000f10a1';
    private const string OPEN       = '01960000-0000-7000-8000-0000000f10a2';
    private const string UNLINKED   = '01960000-0000-7000-8000-0000000f10a3';
    private const string NO_OWNER   = '01960000-0000-7000-8000-0000000f10a4';
    private const string NO_ACCOUNT = '01960000-0000-7000-8000-0000000f10a5';
    private const string UNKNOWN    = '01960000-0000-7000-8000-0000000f10a6';

    private const string OWNER        = '01960000-0000-7000-8000-0000000f10d1';
    private const string OWNER_NO_APP = '01960000-0000-7000-8000-0000000f10d2';

    private KernelBrowser $browser;
    private string $vetId = '';
    private string $token = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique Montmartre')->create();
        $user        = ClinicUserFactory::new()->withEmail('claire.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->vetId = $user->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($this->vetId)->asVeterinary()->asEmployee()->create();

        foreach ([self::OWNER => 'Girard', self::OWNER_NO_APP => 'Roussel'] as $clientId => $lastName) {
            ClientEntityFactory::new()->withId($clientId)->withClinicId(self::CLINIC)->withName('Julien', $lastName)->active()->create();
        }

        $portalUser = PortalUserFactory::new()->withPhone('+33612340001')->create();
        ClientLinkEntityFactory::new()->between($portalUser->getId()->toRfc4122(), self::CLINIC, self::OWNER)->linkedAt(new \DateTimeImmutable('-10 days'))->create();

        $this->consultation(self::CLOSED, ConsultationStatus::CLOSED, $this->animal('Oslo', self::OWNER, OwnershipRole::PRIMARY));
        $this->consultation(self::OPEN, ConsultationStatus::OPEN, $this->animal('Pixel', self::OWNER, OwnershipRole::PRIMARY));
        $this->consultation(self::UNLINKED, ConsultationStatus::CLOSED, null);
        $this->consultation(self::NO_OWNER, ConsultationStatus::CLOSED, $this->animal('Ulysse', self::OWNER, OwnershipRole::SECONDARY));
        $this->consultation(self::NO_ACCOUNT, ConsultationStatus::CLOSED, $this->animal('Pistache', self::OWNER_NO_APP, OwnershipRole::PRIMARY));

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"claire.vet@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
    }

    /** AC 10: closed, linked owner — the toast, the `report` row and « Partagé le … » on the panel. */
    public function testAClosedConsultationIsSharedWithItsLinkedOwner(): void
    {
        self::assertSame(['shareable' => true, 'reason' => null, 'sharedAt' => null], $this->panel(self::CLOSED));

        $answer = $this->share(self::CLOSED);

        self::assertResponseIsSuccessful();
        self::assertSame([true, 'Compte rendu partagé sur le Portal.'], [$answer['success'] ?? null, $answer['message'] ?? null]);
        $rows = $this->rows(self::CLOSED);
        self::assertCount(1, $rows);
        self::assertSame(
            ['report', 'Compte rendu du 30/10/2026', '2026-10-30 10:40:00', 'pending_file', '0', '1', self::OWNER],
            [$rows[0]['type'], $rows[0]['title'], $rows[0]['issued_at'], $rows[0]['status'], $rows[0]['file_version'], $rows[0]['pending_file_version'], $rows[0]['client']],
        );
        self::assertStringStartsWith('Dr ', $rows[0]['practitioner_name'] ?? '', 'the practitioner is snapshotted');

        $portalShare = $answer['portalShare'] ?? null;
        self::assertIsArray($portalShare);
        self::assertTrue($portalShare['shareable']);
        self::assertIsString($portalShare['sharedAt']);
        self::assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4} à \d{2}:\d{2}$#', $portalShare['sharedAt']);
        self::assertSame($portalShare['sharedAt'], $this->panel(self::CLOSED)['sharedAt']);
    }

    /** AC 104: a share keeps the one row and asks for one more version; a double click adds none. */
    public function testSharingAgainMakesANewVersionOfTheSameRow(): void
    {
        $this->share(self::CLOSED);
        $this->connection()->executeStatement(
            "UPDATE client_portal__published_documents SET status = 'available', file_version = 1, pending_file_version = NULL, storage_key = 'client_portal/documents/x-v1.pdf'",
        );

        $answer = $this->share(self::CLOSED);

        self::assertSame('Compte rendu partagé sur le Portal.', $answer['message'] ?? null);
        $rows = $this->rows(self::CLOSED);
        self::assertCount(1, $rows);
        self::assertSame(['pending_file', '1', '2'], [$rows[0]['status'], $rows[0]['file_version'], $rows[0]['pending_file_version']]);

        $this->share(self::CLOSED);
        $rows = $this->rows(self::CLOSED);
        self::assertCount(1, $rows);
        self::assertSame(['1', '2'], [$rows[0]['file_version'], $rows[0]['pending_file_version']], 'a double click while v2 renders adds no version; v1 still served');
    }

    /** AC 104: an owner without Portal account — shared, waiting for their link. */
    public function testAnOwnerWithoutPortalAccountGetsTheReportOnceLinked(): void
    {
        self::assertTrue($this->panel(self::NO_ACCOUNT)['shareable']);

        $answer = $this->share(self::NO_ACCOUNT);

        self::assertResponseIsSuccessful();
        self::assertSame('Partagé — visible dès que le client activera son espace Portal', $answer['message'] ?? null);
        $rows = $this->rows(self::NO_ACCOUNT);
        self::assertSame([['awaiting_link', self::OWNER_NO_APP]], array_map(static fn (array $row): array => [$row['status'], $row['client']], $rows));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideARefusedShareSaysWhyCases(): iterable
    {
        yield 'open consultation' => [self::OPEN, 'La consultation doit être clôturée.'];
        yield 'patient without animal' => [self::UNLINKED, 'Le patient n\'est rattaché à aucun animal.'];
        yield 'animal without primary' => [self::NO_OWNER, 'L\'animal n\'a pas de propriétaire principal.'];
    }

    /** AC 10: the button is disabled with the reason, and a forced POST is refused with it. */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideARefusedShareSaysWhyCases')]
    public function testARefusedShareSaysWhy(string $consultationId, string $reason): void
    {
        self::assertSame(['shareable' => false, 'reason' => $reason, 'sharedAt' => null], $this->panel($consultationId));

        $answer = $this->share($consultationId);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([false, $reason], [$answer['success'] ?? null, $answer['error'] ?? null]);
        self::assertSame([], $this->rows($consultationId));
    }

    public function testAnUnknownConsultationOrAnExpiredSessionIsRefused(): void
    {
        $this->panel(self::CLOSED);

        self::assertSame('Consultation introuvable.', $this->share(self::UNKNOWN)['error'] ?? null);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Consultation introuvable.', $this->share('not-a-uuid')['error'] ?? null);
        self::assertResponseStatusCodeSame(404);

        $this->token = 'forged';
        self::assertSame('Session expirée, rechargez la page.', $this->share(self::CLOSED)['error'] ?? null);
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->rows(self::CLOSED));
    }

    /**
     * The `documents.portalShare` the page hands to its module, keeping the
     * CSRF token of the page.
     *
     * @return array<mixed>
     */
    private function panel(string $consultationId): array
    {
        $crawler = $this->browser->request('GET', self::HOST . '/clinic/consultations/' . $consultationId);
        self::assertResponseIsSuccessful();
        $this->token = (string) $crawler->filter('#consultation-csrf')->attr('value');
        $data        = json_decode($crawler->filter('#consultation-data')->text(), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($data) && \is_array($data['documents'] ?? null) && \is_array($data['documents']['portalShare'] ?? null));
        $portalShare = $data['documents']['portalShare'];
        self::assertSame('/clinic/consultations/' . $consultationId . '/documents/portal-share', $portalShare['url']);
        unset($portalShare['url']);

        return $portalShare;
    }

    /**
     * @return array<mixed>
     */
    private function share(string $consultationId): array
    {
        if ('' === $this->token) {
            $this->panel(self::CLOSED);
        }

        $this->browser->request('POST', self::HOST . '/clinic/consultations/' . $consultationId . '/documents/portal-share', ['_token' => $this->token], server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $answer = json_decode((string) $this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($answer));

        return $answer;
    }

    /**
     * The registry rows of a consultation, every value as text.
     *
     * @return list<array<string, ?string>>
     */
    private function rows(string $consultationId): array
    {
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT type, title, issued_at, status, file_version, pending_file_version, BIN_TO_UUID(client_id) AS client, practitioner_name
             FROM client_portal__published_documents WHERE source_ref = ?',
            [Uuid::fromString($consultationId)->toBinary()],
        );

        return array_map(static fn (array $row): array => array_map(static fn (mixed $value): ?string => \is_scalar($value) ? (string) $value : null, $row), $rows);
    }

    private function animal(string $name, string $clientId, OwnershipRole $role): string
    {
        $id = Uuid::v7();
        AnimalEntityFactory::new()->withClinicId(self::CLINIC)->withName($name)->dog()->active()->alive()->create(['id' => $id]);
        OwnershipEntityFactory::createOne([
            'animal'   => AnimalEntityFactory::find(['id' => $id]),
            'clientId' => Uuid::fromString($clientId),
            'role'     => $role,
        ]);

        return $id->toRfc4122();
    }

    private function consultation(string $id, ConsultationStatus $status, ?string $animalId): void
    {
        $patientId   = Uuid::v7()->toRfc4122();
        $admissionId = Uuid::v7()->toRfc4122();
        $patient     = PatientEntityFactory::new()->withId($patientId)->withClinicId(self::CLINIC);
        (null === $animalId ? $patient->provisional('Chien noir') : $patient->identified()->withAnimalLinkId($animalId))->create();
        AdmissionEntityFactory::new()->withId($admissionId)->withClinicId(self::CLINIC)->withPatientId($patientId)->create();

        $utc = new \DateTimeZone('UTC');
        ConsultationEntityFactory::new()
            ->withId($id)
            ->withClinicId(self::CLINIC)
            ->withAdmissionId($admissionId)
            ->withPatientId($patientId)
            ->withPractitionerUserId($this->vetId)
            ->withStatus($status)
            ->create([
                'startedAtUtc' => new \DateTimeImmutable('2026-10-30 10:00', $utc),
                'closedAtUtc'  => ConsultationStatus::CLOSED === $status ? new \DateTimeImmutable('2026-10-30 10:40', $utc) : null,
                'createdAtUtc' => new \DateTimeImmutable('2026-10-30 10:00', $utc),
                'updatedAtUtc' => new \DateTimeImmutable('2026-10-30 10:40', $utc),
            ])
        ;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
