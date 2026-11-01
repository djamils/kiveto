<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\Prescription\Domain\Prescription;
use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\DrugPrescriptionClass;
use App\Context\Prescription\Domain\ValueObject\OwnerId;
use App\Context\Prescription\Domain\ValueObject\OwnerSnapshot;
use App\Context\Prescription\Domain\ValueObject\PatientSnapshot;
use App\Context\Prescription\Domain\ValueObject\Prescriber;
use App\Context\Prescription\Domain\ValueObject\PrescriberId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLine;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLineId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionNumber;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 6 setup on top of slice 4's: issued prescriptions written through
 * the Prescription repository with their row in the publication registry,
 * a second owner of the same clinic and the PUT verb. Today is Monday
 * 2 November 2026, 11:00 in Paris (10:00 UTC).
 */
abstract class RequestsApiTestCase extends BookingApiTestCase
{
    private static int $number = 600;

    /**
     * An issued prescription of the clinic for the pet, published to the
     * client; issued a year and a half ago unless told otherwise, so expired.
     */
    protected function prescription(
        string $petId,
        string $clientId = self::CLIENT,
        bool $controlled = false,
        string $issuedAtUtc = '2025-05-04 09:00:00',
        bool $cancelled = false,
        ?string $prescriberId = null,
    ): string {
        $id  = Uuid::v7()->toRfc4122();
        $at  = new \DateTimeImmutable($issuedAtUtc, new \DateTimeZone('UTC'));
        $doc = Prescription::draft(
            id: PrescriptionId::fromString($id),
            clinicId: ClinicId::fromString(self::CLINIC),
            consultationId: ConsultationId::fromString(Uuid::v7()->toRfc4122()),
            prescriber: Prescriber::create(PrescriberId::fromString($prescriberId ?? Uuid::v7()->toRfc4122()), 'Rousseau', 'FR-12345', 'DR'),
            patient: PatientSnapshot::create(animalId: $petId, name: 'Rex', species: 'dog', breed: null, birthDate: null, weightKg: null, microchipNumber: null),
            owner: OwnerSnapshot::create(OwnerId::fromString($clientId), 'Sophie Dupont', ['12 rue des Lilas']),
            now: $at,
        );
        $doc->addLine(PrescriptionLine::create(
            id: PrescriptionLineId::fromString(Uuid::v7()->toRfc4122()),
            articleId: null,
            code: 'CODE-1',
            label: $controlled ? 'Kétamine' : 'Meloxidyl',
            pharmaceuticalForm: 'Suspension buvable',
            activeSubstances: ['Méloxicam'],
            dose: '0,1 mg/kg',
            frequency: '1×/j',
            durationDays: 7,
            route: 'per os',
            quantity: '1',
            prescriptionClass: $controlled ? DrugPrescriptionClass::RX_NARCOTIC : DrugPrescriptionClass::RX,
        ), $at);
        $doc->issue(PrescriptionNumber::of(2025, ++self::$number), $at);

        if ($cancelled) {
            $doc->cancel('Erreur de saisie', $at);
        }

        $this->service(PrescriptionRepositoryInterface::class)->save($doc);

        $this->service(PublishedDocumentRepositoryInterface::class)->save(PublishedDocument::reconstitute(
            id: PublishedDocumentId::fromString(Uuid::v7()->toRfc4122()),
            clinicId: self::CLINIC,
            clientId: $clientId,
            type: DocumentType::PRESCRIPTION,
            sourceRef: $id,
            consultationId: null,
            patientId: null,
            petIds: [$petId],
            title: 'Ordonnance du ' . $at->format('d/m/Y'),
            issuedAt: $at,
            practitionerName: 'Dr Claire Rousseau',
            storageKey: 'client_portal/documents/' . $id . '-v1.pdf',
            fileVersion: 1,
            pendingFileVersion: null,
            renderAttempts: 0,
            status: PublicationStatus::AVAILABLE,
            publishedAt: $at,
            updatedAt: $at,
        ));

        return $id;
    }

    /**
     * A second owner signed in and linked to the other client of the clinic.
     *
     * @return string its access token
     */
    protected function otherOwner(string $phone): string
    {
        PortalUserFactory::new()->withPhone($phone)->create();
        $token   = self::string($this->signIn($phone)['accessToken']);
        $account = self::json($this->get('/me', ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]), 200)['account'] ?? null;
        self::assertIsArray($account);

        $this->service(ClientLinkRepositoryInterface::class)->save(
            ClientLink::confirm(ClientLinkId::fromString(Uuid::v7()->toRfc4122()), self::string($account['id']), self::CLINIC, self::OTHER_CLIENT, new \DateTimeImmutable('2026-10-01')),
        );

        return $token;
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function put(string $path, array $body): Response
    {
        $this->browser->request('PUT', self::BASE . $path, server: [
            'CONTENT_TYPE'       => 'application/json',
            'HTTP_ACCEPT'        => 'application/json',
            'REMOTE_ADDR'        => $this->clientIp,
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        return $this->browser->getResponse();
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return list<array<string, mixed>>
     */
    protected static function items(array $page): array
    {
        return self::rows($page['items'] ?? null);
    }
}
