<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\PortalRequest;

use App\Context\Animal\Domain\ValueObject\OwnershipRole;
use App\Context\ClientPortal\Application\Command\SubmitPetChangeRequest\SubmitPetChangeRequest;
use App\Context\ClientPortal\Application\Command\SubmitRenewalRequest\SubmitRenewalRequest;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic\ListPermissionsForUserInClinic;
use App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic\ListPermissionsForUserInClinicHandler;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
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
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\ClientLinkEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Shared\Time\FrozenClock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * A clinic whose client Sophie Dupont reaches it from the Portal, with her
 * dog Rex; requests are submitted through the ClientPortal commands, as the
 * Portal API does. Today is Monday 2 November 2026, 11:00 in Paris.
 */
abstract class PortalRequestTestCase extends WebTestCase
{
    use Factories;

    protected const string CLINIC = '01960000-0000-7000-8000-0000000d6c01';
    protected const string OTHER  = '01960000-0000-7000-8000-0000000d6c02';
    protected const string CLIENT = '01960000-0000-7000-8000-0000000d6d01';
    protected const string HOST   = 'http://clinic.kiveto.local';

    protected const string XSS = '<img src=x onerror=alert(1)>';

    protected KernelBrowser $browser;
    protected string $vetId   = '';
    protected string $ownerId = '';
    protected string $rex     = '';

    private static int $number = 900;

    /**
     * The permissions Clinic leaves out of the member's: its handler is
     * wrapped before anything asks for them, as it cannot be replaced once
     * built.
     *
     * @var \ArrayObject<int, string>
     */
    private \ArrayObject $removed;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        self::getContainer()->set(ClockInterface::class, new FrozenClock(new \DateTimeImmutable('2026-11-02 10:00:00', new \DateTimeZone('UTC'))));

        $real          = new ListPermissionsForUserInClinicHandler($this->service(ClinicMembershipRepositoryInterface::class), $this->service(ClockInterface::class));
        $this->removed = new \ArrayObject();
        $permissions   = new readonly class($real, $this->removed) {
            /**
             * @param \ArrayObject<int, string> $removed
             */
            public function __construct(private ListPermissionsForUserInClinicHandler $real, private \ArrayObject $removed)
            {
            }

            /**
             * @return list<string>
             */
            public function __invoke(ListPermissionsForUserInClinic $query): array
            {
                return array_values(array_diff(($this->real)($query), $this->removed->getArrayCopy()));
            }
        };
        self::getContainer()->set(ListPermissionsForUserInClinicHandler::class, $permissions);

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Tilleuls')->create(['timeZone' => 'Europe/Paris']);
        ClinicEntityFactory::new()->withId(self::OTHER)->withName('Clinique du Port')->create(['timeZone' => 'Europe/Paris']);

        $vet         = ClinicUserFactory::new()->withEmail('portal.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->vetId = $vet->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($this->vetId)->asVeterinary()->asEmployee()->create();

        ClientEntityFactory::new()->withId(self::CLIENT)->withClinicId(self::CLINIC)->withName('Sophie', 'Dupont')->active()->create();
        $owner         = PortalUserFactory::new()->withPhone('+33612349001')->create();
        $this->ownerId = $owner->getId()->toRfc4122();
        ClientLinkEntityFactory::new()->between($this->ownerId, self::CLINIC, self::CLIENT)->linkedAt(new \DateTimeImmutable('2026-10-01'))->create();

        $this->rex = $this->pet('Rex');
    }

    protected function pet(string $name): string
    {
        $id = Uuid::v7();
        AnimalEntityFactory::new()->withClinicId(self::CLINIC)->withName($name)->dog()->active()->alive()->create(['id' => $id]);
        OwnershipEntityFactory::createOne([
            'animal'   => AnimalEntityFactory::find(['id' => $id]),
            'clientId' => Uuid::fromString(self::CLIENT),
            'role'     => OwnershipRole::PRIMARY,
        ]);

        return $id->toRfc4122();
    }

    /** A pet change submitted by Sophie from the Portal; its id. */
    protected function change(string $field, string $value, ?string $note = null, ?string $petId = null, ?string $measuredOn = null): string
    {
        $id = $this->service(CommandBusInterface::class)->dispatch(new SubmitPetChangeRequest(
            $this->ownerId,
            self::CLINIC,
            $petId ?? $this->rex,
            $field,
            $value,
            null === $measuredOn ? null : new \DateTimeImmutable($measuredOn),
            $note,
        ));
        \assert(\is_string($id));

        return $id;
    }

    /** A renewal of a prescription of Rex published to Sophie; its id. */
    protected function renewal(string $condition = 'improved', ?string $note = null, ?string $petId = null): string
    {
        $petId ??= $this->rex;
        $id = $this->service(CommandBusInterface::class)->dispatch(new SubmitRenewalRequest(
            $this->ownerId,
            self::CLINIC,
            $this->prescription($petId, '2026-09-01 09:00:00', published: true),
            $condition,
            $note,
        ));
        \assert(\is_string($id));

        return $id;
    }

    /** An issued prescription of the clinic for the pet; its id. */
    protected function prescription(string $petId, string $issuedAtUtc, bool $published = false): string
    {
        $id  = Uuid::v7()->toRfc4122();
        $at  = new \DateTimeImmutable($issuedAtUtc, new \DateTimeZone('UTC'));
        $doc = Prescription::draft(
            id: PrescriptionId::fromString($id),
            clinicId: ClinicId::fromString(self::CLINIC),
            consultationId: ConsultationId::fromString(Uuid::v7()->toRfc4122()),
            prescriber: Prescriber::create(PrescriberId::fromString($this->vetId), 'Rousseau', 'FR-12345', 'DR'),
            patient: PatientSnapshot::create(animalId: $petId, name: 'Rex', species: 'dog', breed: null, birthDate: null, weightKg: null, microchipNumber: null),
            owner: OwnerSnapshot::create(OwnerId::fromString(self::CLIENT), 'Sophie Dupont', ['12 rue des Lilas']),
            now: $at,
        );
        $doc->addLine(PrescriptionLine::create(
            id: PrescriptionLineId::fromString(Uuid::v7()->toRfc4122()),
            articleId: null,
            code: 'CODE-1',
            label: 'Meloxidyl',
            pharmaceuticalForm: 'Suspension buvable',
            activeSubstances: ['Méloxicam'],
            dose: '0,1 mg/kg',
            frequency: '1×/j',
            durationDays: 7,
            route: 'per os',
            quantity: '1',
            prescriptionClass: DrugPrescriptionClass::RX,
        ), $at);
        $doc->issue(PrescriptionNumber::of(2026, ++self::$number), $at);
        $this->service(PrescriptionRepositoryInterface::class)->save($doc);

        if ($published) {
            $this->service(PublishedDocumentRepositoryInterface::class)->save(PublishedDocument::reconstitute(
                id: PublishedDocumentId::fromString(Uuid::v7()->toRfc4122()),
                clinicId: self::CLINIC,
                clientId: self::CLIENT,
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
        }

        return $id;
    }

    protected function signIn(): void
    {
        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"portal.vet@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
    }

    /** Clinic answers the member's permissions without the given ones. */
    protected function withoutPermissions(string ...$removed): void
    {
        $this->removed->exchangeArray(array_values($removed));
    }

    protected function page(string $path): string
    {
        $this->browser->request('GET', self::HOST . $path);

        return (string) $this->browser->getResponse()->getContent();
    }

    /**
     * The page module's payload of a « Demandes » page.
     *
     * @return array<array-key, mixed>
     */
    protected function payload(string $path): array
    {
        $crawler = $this->browser->request('GET', self::HOST . $path);
        self::assertResponseIsSuccessful();

        return self::arr(json_decode((string) $crawler->filter('[data-payload]')->attr('data-payload'), true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * A JSON answer of the page module's endpoints.
     *
     * @return array<array-key, mixed>
     */
    protected function json(string $path): array
    {
        $this->browser->request('GET', self::HOST . $path, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        return self::arr(json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * The sheet open on a request page.
     *
     * @return array<array-key, mixed>
     */
    protected function sheet(string $path): array
    {
        return self::arr($this->payload($path)['sheet']);
    }

    /**
     * The rows of the register a page shows.
     *
     * @return list<array<array-key, mixed>>
     */
    protected function rows(string $path): array
    {
        return self::rowsOf(self::arr($this->payload($path)['register']));
    }

    /**
     * The ids of the rows of the register a page shows.
     *
     * @return list<mixed>
     */
    protected function ids(string $path): array
    {
        return array_column($this->rows($path), 'id');
    }

    /**
     * @param array<array-key, mixed> $register
     *
     * @return list<array<array-key, mixed>>
     */
    protected static function rowsOf(array $register): array
    {
        $rows = $register['rows'];
        \assert(\is_array($rows));

        return array_values(array_map(self::arr(...), $rows));
    }

    /**
     * The given keys of a row, in that order.
     *
     * @param array<array-key, mixed> $row
     *
     * @return array<string, mixed>
     */
    protected static function pick(array $row, string ...$keys): array
    {
        return array_combine($keys, array_map(static fn (string $key): mixed => $row[$key] ?? null, $keys));
    }

    /**
     * @return array<array-key, mixed>
     */
    protected static function arr(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }

    /** The token of the answer form of a request page. */
    protected function token(string $requestId): string
    {
        $token = self::arr($this->payload('/portal-requests/' . $requestId)['tokens'])['request'];
        \assert(\is_string($token));

        return $token;
    }

    /**
     * @param array<string, string> $fields
     */
    protected function resolve(string $requestId, array $fields, ?string $token = null): void
    {
        $this->browser->request('POST', self::HOST . '/portal-requests/' . $requestId . '/resolve', ['_token' => $token ?? $this->token($requestId), ...$fields]);
    }

    /** The flash messages the next page shows. */
    protected function flashes(): string
    {
        $crawler = $this->browser->followRedirect();

        return implode("\n", $crawler->filter('[data-flash-toast]')->each(static fn ($node): string => (string) $node->attr('data-message')));
    }

    protected function connection(): Connection
    {
        return $this->service(Connection::class);
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
        $service = self::getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }
}
