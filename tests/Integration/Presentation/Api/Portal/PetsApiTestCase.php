<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Animal\Domain\ValueObject\AnimalStatus;
use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Domain\ValueObject\TransferStatus;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Animal\Factory\PreventiveCareRecordEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Unit\Context\ClientPortal\Support\SettableClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 3 setup: a clinic in Europe/Paris, an owner account signed in and
 * confirmed-linked to one client file, animals built through the Animal
 * fixture factories and report rows written to the publication registry.
 * Both clocks (the application's and the framework's, read by the URL
 * signer) are frozen on 2 November 2026, 10:00 UTC.
 */
abstract class PetsApiTestCase extends PortalApiTestCase
{
    use ClockSensitiveTrait;

    protected const string NOW          = '2026-11-02 10:00:00';
    protected const string CLINIC       = '01930000-0000-7000-8000-00000000c301';
    protected const string OTHER_CLINIC = '01930000-0000-7000-8000-00000000c302';
    protected const string CLIENT       = '01930000-0000-7000-8000-00000000d301';
    protected const string OTHER_CLIENT = '01930000-0000-7000-8000-00000000d302';

    protected string $token;

    protected string $userId;

    protected SettableClock $clock;

    protected function setUp(): void
    {
        $now = new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'));
        self::mockTime($now);
        parent::setUp();
        $this->clock = new SettableClock($now);
        $this->browser->getContainer()->set(ClockInterface::class, $this->clock);

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Lilas')->create(['timeZone' => $this->clinicTimeZone(), ...$this->clinicAttributes()]);
        ClinicEntityFactory::new()->withId(self::OTHER_CLINIC)->withName('Clinique du Port')->create();
        PortalUserFactory::new()->withPhone($this->phone())->create();

        $this->token = self::string($this->signIn($this->phone())['accessToken']);
        $account     = self::json($this->authGet('/me'), 200)['account'] ?? null;
        self::assertIsArray($account);
        $this->userId = self::string($account['id'] ?? null);

        $this->link(self::CLIENT);
    }

    abstract protected function phone(): string;

    /** Moves both clocks to an instant (UTC). */
    protected function travelTo(string $utc): void
    {
        $now = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        self::mockTime($now);
        $this->clock->now = $now;
    }

    protected function clinicTimeZone(): string
    {
        return 'Europe/Paris';
    }

    /**
     * ClinicEntity overrides of the linked clinic.
     *
     * @return array<string, mixed>
     */
    protected function clinicAttributes(): array
    {
        return [];
    }

    protected function link(string $clientId, string $clinicId = self::CLINIC): void
    {
        $this->service(ClientLinkRepositoryInterface::class)->save(
            ClientLink::confirm(ClientLinkId::fromString(Uuid::v7()->toRfc4122()), $this->userId, $clinicId, $clientId, new \DateTimeImmutable('2026-10-01')),
        );
    }

    /**
     * An animal of the clinic whose primary owner is the client.
     *
     * @param array<string, mixed> $attributes AnimalEntity overrides
     */
    protected function pet(string $name, string $clientId = self::CLIENT, array $attributes = [], bool $primary = true, string $clinicId = self::CLINIC): string
    {
        $id     = Uuid::v7()->toRfc4122();
        $animal = AnimalEntityFactory::new()->withId($id)->withClinicId($clinicId)->create([
            'name'            => $name,
            'status'          => AnimalStatus::ACTIVE,
            'lifeStatus'      => LifeStatus::ALIVE,
            'transferStatus'  => TransferStatus::NONE,
            'microchipNumber' => null,
            'birthDate'       => null,
            'breedName'       => null,
            'isMixedBreed'    => false,
            ...$attributes,
        ]);

        $ownership = OwnershipEntityFactory::new()->withClientId($clientId)->active();
        ($primary ? $ownership->primary() : $ownership->secondary())->create(['animal' => $animal]);

        return $id;
    }

    protected function careAct(string $petId, PreventiveCareKind $kind, string $label, string $administeredOn, ?string $nextDueOn): void
    {
        PreventiveCareRecordEntityFactory::new()
            ->forAnimal(self::CLINIC, $petId)
            ->act($kind, $label, new \DateTimeImmutable($administeredOn), null === $nextDueOn ? null : new \DateTimeImmutable($nextDueOn))
            ->create()
        ;
    }

    /**
     * A report row of the publication registry, listable.
     *
     * @param list<string> $petIds
     */
    protected function report(string $clientId, string $issuedAt, array $petIds, ?string $consultationId = null, ?string $vet = 'Dr Claire Lambert', string $clinicId = self::CLINIC): string
    {
        return $this->document(DocumentType::REPORT, $clientId, $issuedAt, $petIds, $consultationId, $vet, $clinicId);
    }

    /**
     * @param list<string> $petIds
     */
    protected function document(DocumentType $type, string $clientId, string $issuedAt, array $petIds, ?string $consultationId = null, ?string $vet = 'Dr Claire Lambert', string $clinicId = self::CLINIC): string
    {
        $id = Uuid::v7()->toRfc4122();
        $at = new \DateTimeImmutable($issuedAt, new \DateTimeZone('UTC'));

        $this->service(PublishedDocumentRepositoryInterface::class)->save(PublishedDocument::reconstitute(
            id: PublishedDocumentId::fromString($id),
            clinicId: $clinicId,
            clientId: $clientId,
            type: $type,
            sourceRef: DocumentType::REPORT === $type && null !== $consultationId ? $consultationId : Uuid::v7()->toRfc4122(),
            consultationId: $consultationId,
            patientId: null,
            petIds: $petIds,
            title: 'Compte rendu du ' . $at->format('d/m/Y'),
            issuedAt: $at,
            practitionerName: $vet,
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
     * @return list<array<string, mixed>>
     */
    protected static function list(Response $response): array
    {
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertTrue(array_is_list($data), 'a plain JSON array');

        return self::rows($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function rows(mixed $rows): array
    {
        self::assertIsArray($rows);

        $list = [];
        foreach ($rows as $row) {
            self::assertIsArray($row);
            $fields = [];
            foreach ($row as $key => $value) {
                $fields[(string) $key] = $value;
            }
            $list[] = $fields;
        }

        return $list;
    }

    protected function authGet(string $path): Response
    {
        return $this->get($path, ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected function service(string $id): object
    {
        $service = $this->browser->getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }
}
