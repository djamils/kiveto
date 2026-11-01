<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Export;

use App\Context\ClientPortal\Application\Port\AppointmentScope;
use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\OwnerAccount;
use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Port\UpcomingAppointment;
use App\Context\ClientPortal\Application\Service\PortalAppointments;
use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestId;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\RequestResolution;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Infrastructure\Export\DataExportBuilder;
use App\Shared\Application\Pagination\CursorPage;
use App\Shared\Domain\ValueObject\PostalAddress;
use App\Tests\Support\Document\CapturingDocumentPdfRenderer;
use App\Tests\Unit\Context\ClientPortal\Support\DataExportFakes;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryBookingRequests;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryChangeRequests;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryOwnerProfiles;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryQuestionnaires;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryRenewalRequests;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use PHPUnit\Framework\TestCase;

/**
 * The canonical content of an export (S5f, AC 63, AC 210): the account is
 * confirmed-linked to CLIENT of the Lyon clinic (Europe/Paris) and was
 * linked to a clinic that withdrew the access; the clock is at
 * 2026-10-29 09:00 UTC.
 */
final class DataExportBuilderTest extends TestCase
{
    public const string UPCOMING        = '01960000-0000-7000-8000-000000000101';
    public const string PAST            = '01960000-0000-7000-8000-000000000102';
    public const string FOREIGN         = '01960000-0000-7000-8000-000000000103';
    public const string PAST_2          = '01960000-0000-7000-8000-000000000104';
    private const string REVOKED_CLINIC = '01960000-0000-7000-8000-0000000c0009';
    private const string VET            = '01960000-0000-7000-8000-00000000d001';
    private const string OTHER_USER     = '01960000-0000-7000-8000-0000000c0099';
    private const string PRESCRIPTION   = '01960000-0000-7000-8000-0000000b0001';
    private const string RESULTING      = '01960000-0000-7000-8000-0000000b0002';
    private const string APPOINTMENT    = '01960000-0000-7000-8000-0000000b0003';
    private const string RENEWAL        = '01960000-0000-7000-8000-0000000e0001';
    private const string CHANGE         = '01960000-0000-7000-8000-0000000e0002';
    private const string OTHER_CHANGE   = '01960000-0000-7000-8000-0000000e0003';
    private const string OLD_CHANGE     = '01960000-0000-7000-8000-0000000e0004';
    private const string QUESTIONNAIRE  = '01960000-0000-7000-8000-0000000e0005';

    private W $world;
    private DataExportFakes $fakes;
    private InMemoryOwnerProfiles $profiles;
    private CapturingDocumentPdfRenderer $pdf;
    private InMemoryChangeRequests $changes;
    private InMemoryRenewalRequests $renewals;
    private InMemoryQuestionnaires $questionnaires;
    private InMemoryBookingRequests $bookingRequests;

    /** @var list<string> */
    private array $pages = [];

    protected function setUp(): void
    {
        $this->world                          = new W();
        $this->fakes                          = new DataExportFakes();
        $this->profiles                       = new InMemoryOwnerProfiles();
        $this->pdf                            = new CapturingDocumentPdfRenderer();
        $this->changes                        = new InMemoryChangeRequests();
        $this->renewals                       = new InMemoryRenewalRequests();
        $this->questionnaires                 = new InMemoryQuestionnaires();
        $this->bookingRequests                = new InMemoryBookingRequests();
        $this->fakes->accounts[W::USER]       = new OwnerAccount(W::USER, '+33612345678', 'sophie@example.com', true);
        $this->world->documents->links->links = [];
    }

    public function testTheArchiveHoldsTheAccountEachClinicAndTheSummary(): void
    {
        $this->profiles->save(OwnerProfile::createFromClient(W::USER, 'Sophie', 'Martin', PostalAddress::create('12 rue des Lilas', 'Lyon', 'FR', 'Bât. B', '69003'), 'v2', new \DateTimeImmutable('2026-10-01 08:00:00', new \DateTimeZone('UTC'))));
        $this->link(W::CLINIC, W::CLIENT, ClientLinkStatus::CONFIRMED, '2026-10-01 08:00:00', null);
        $this->link(self::REVOKED_CLINIC, W::CLIENT2, ClientLinkStatus::REVOKED, '2026-05-01 08:00:00', '2026-09-01 08:00:00');
        $this->world->documents->fakes->clinics[self::REVOKED_CLINIC] = new ClinicLabel('Clinique du Port', null, 'America/Martinique');
        $this->world->pet();
        $this->world->pet(W::PET3, 'Filou', 'alive', W::CLIENT2);
        $this->world->fakes->practitioners = [self::VET => 'Dr Claire Lambert'];
        $this->world->model->rows          = [W::report('01960000-0000-7000-8000-0000000f0001', '01960000-0000-7000-8000-0000000f9001', '2026-10-20 08:30:00')];
        $this->fakes->consents[W::CLINIC]  = [
            'consents' => [['kind' => 'appointment_reminders', 'channels' => ['sms', 'email']]],
            'history'  => [['kind' => 'appointment_reminders', 'channel' => 'email', 'action' => 'granted', 'source' => 'portal', 'at' => new \DateTimeImmutable('2026-10-10 12:00:00', new \DateTimeZone('UTC'))]],
        ];

        // The account's requests and questionnaires, in their clinic; another account's are not exported
        $renewal = Requests::renewal(self::RENEWAL, self::PRESCRIPTION, at: '2026-10-20 08:00:00');
        $renewal->resolve(RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', 'Nouvelle ordonnance prête.', Requests::utc('2026-10-21 08:00:00')), self::RESULTING);
        $this->renewals->save($renewal);
        $this->changes->save(Requests::change(self::CHANGE, at: '2026-10-19 08:00:00'));
        $this->changes->save(Requests::change(self::OTHER_CHANGE, userId: self::OTHER_USER));
        $this->changes->save(Requests::change(self::OLD_CHANGE, petId: W::PET3, field: ChangeField::NAME, value: 'Filou II', at: '2026-06-01 08:00:00', clientId: W::CLIENT2, clinicId: self::REVOKED_CLINIC, note: null));
        $this->questionnaires->save(Requests::questionnaire(self::QUESTIONNAIRE, self::APPOINTMENT, '2026-10-27 08:00:00', answers: Requests::answers('may_bite')));

        $files = $this->unzip($this->builder()->build(W::USER));

        self::assertSame(['account.json', 'clinics/' . W::CLINIC . '.json', 'clinics/' . self::REVOKED_CLINIC . '.json', 'summary.pdf'], array_keys($files));
        self::assertSame([
            'schemaVersion' => 1,
            'generatedAt'   => '2026-10-29T09:00:00+00:00',
            'account'       => ['id' => W::USER, 'phone' => '+33612345678', 'email' => 'sophie@example.com', 'emailVerified' => true],
            'profile'       => [
                'firstName' => 'Sophie',
                'lastName'  => 'Martin',
                'address'   => ['streetLine1' => '12 rue des Lilas', 'streetLine2' => 'Bât. B', 'postalCode' => '69003', 'city' => 'Lyon', 'countryCode' => 'FR'],
            ],
            'terms'           => ['version' => 'v2', 'acceptedAt' => '2026-10-01T08:00:00+00:00'],
            'clinics'         => [W::CLINIC, self::REVOKED_CLINIC],
            'bookingRequests' => [],
        ], self::decode($files['account.json']));

        self::assertSame([
            'schemaVersion'  => 1,
            'clinic'         => ['id' => W::CLINIC, 'name' => 'Clinique des Lilas', 'city' => 'Lyon'],
            'links'          => [['status' => 'confirmed', 'linkedAt' => '2026-10-01T10:00:00+02:00', 'revokedAt' => null]],
            'consents'       => [['kind' => 'appointment_reminders', 'channels' => ['sms', 'email']]],
            'consentHistory' => [['kind' => 'appointment_reminders', 'channel' => 'email', 'action' => 'granted', 'source' => 'portal', 'at' => '2026-10-10T14:00:00+02:00']],
            'appointments'   => [
                ['startAt' => '2026-11-05T10:00:00+01:00', 'pet' => 'Moka', 'motif' => 'Rappel vaccin', 'status' => 'confirmed'],
                ['startAt' => '2026-11-05T10:00:00+01:00', 'pet' => null, 'motif' => 'Rendez-vous', 'status' => 'completed'],
                ['startAt' => '2026-11-05T10:00:00+01:00', 'pet' => 'Moka', 'motif' => 'Rendez-vous', 'status' => 'no_show'],
            ],
            'documents' => [['type' => 'report', 'title' => 'Compte rendu 01960000-0000-7000-8000-0000000f0001', 'issuedAt' => '2026-10-20T10:30:00+02:00']],
            'requests'  => [
                [
                    'kind'              => 'pet_change', 'petId' => W::PET, 'field' => 'weight', 'proposedValue' => '12,4', 'prescriptionId' => null, 'petCondition' => null,
                    'note'              => 'Pesé à la maison', 'status' => 'submitted', 'submittedAt' => '2026-10-19T10:00:00+02:00', 'resolvedAt' => null,
                    'resolutionMessage' => null, 'resultingPrescriptionId' => null,
                ],
                [
                    'kind'              => 'prescription_renewal', 'petId' => W::PET, 'field' => null, 'proposedValue' => null, 'prescriptionId' => self::PRESCRIPTION, 'petCondition' => 'improved',
                    'note'              => 'Va mieux', 'status' => 'done', 'submittedAt' => '2026-10-20T10:00:00+02:00', 'resolvedAt' => '2026-10-21T10:00:00+02:00',
                    'resolutionMessage' => 'Nouvelle ordonnance prête.', 'resultingPrescriptionId' => self::RESULTING,
                ],
            ],
            'questionnaires' => [[
                'appointmentId'    => self::APPOINTMENT, 'version' => 1, 'status' => 'submitted', 'symptoms' => ['cough'],
                'ongoingTreatment' => ['has' => true, 'details' => 'Sirop'], 'temperament' => 'may_bite', 'notes' => 'Il tousse la nuit.',
                'updatedAt'        => '2026-10-27T09:00:00+01:00',
            ]],
        ], self::decode($files['clinics/' . W::CLINIC . '.json']));
        self::assertSame([W::CLINIC . ':' . W::CLIENT], $this->fakes->consentCalls);
        self::assertSame(['upcoming:-', 'past:-', 'past:p2'], $this->pages, 'both scopes, every page');

        // A clinic that withdrew the access: the link only.
        self::assertSame([
            'schemaVersion'  => 1,
            'clinic'         => ['id' => self::REVOKED_CLINIC, 'name' => 'Clinique du Port', 'city' => null],
            'links'          => [['status' => 'revoked', 'linkedAt' => '2026-05-01T04:00:00-04:00', 'revokedAt' => '2026-09-01T04:00:00-04:00']],
            'consents'       => [],
            'consentHistory' => [],
            'appointments'   => [],
            'documents'      => [],
            'requests'       => [[
                'kind'              => 'pet_change', 'petId' => W::PET3, 'field' => 'name', 'proposedValue' => 'Filou II', 'prescriptionId' => null, 'petCondition' => null,
                'note'              => null, 'status' => 'submitted', 'submittedAt' => '2026-06-01T04:00:00-04:00', 'resolvedAt' => null,
                'resolutionMessage' => null, 'resultingPrescriptionId' => null,
            ]],
            'questionnaires' => [],
        ], self::decode($files['clinics/' . self::REVOKED_CLINIC . '.json']));

        self::assertSame('%PDF portal/export/summary.html.twig', $files['summary.pdf']);
        $summary = $this->pdf->last();
        self::assertSame(['Export de vos données', null], [$summary['footer']->reference, $summary['footer']->clinic]);
        self::assertSame(['account', 'clinics', 'generatedAt'], array_keys($summary['context']));
        $generatedAt = $summary['context']['generatedAt'];
        self::assertInstanceOf(\DateTimeImmutable::class, $generatedAt);
        self::assertSame('2026-10-29T10:00:00+01:00', $generatedAt->format('c'));
    }

    public function testAnAccountWithoutLinkGetsTheAccountAndTheSummaryOnly(): void
    {
        $files = $this->unzip($this->builder()->build(W::USER));

        self::assertSame(['account.json', 'summary.pdf'], array_keys($files));
        $account = self::decode($files['account.json']);
        self::assertSame([null, null, []], [$account['profile'], $account['terms'], $account['clinics']]);
        self::assertSame([], $this->fakes->consentCalls);
    }

    /** S5f, slice 7: the new-client booking requests, any clinic (linked or not), in account.json. */
    public function testTheBookingRequestsOfTheAccountAreExported(): void
    {
        $this->world->documents->fakes->clinics[self::REVOKED_CLINIC] = new ClinicLabel('Clinique du Port', 'Fort-de-France', 'America/Martinique');
        $utc                                                          = new \DateTimeZone('UTC');
        $request                                                      = BookingRequest::request(
            BookingRequestId::fromString('01960000-0000-7000-8000-0000000e0f01'),
            W::USER,
            self::REVOKED_CLINIC,
            '01960000-0000-7000-8000-0000000e0f02',
            '01960000-0000-7000-8000-0000000e0f03',
            'Première consultation',
            new \DateTimeImmutable('2026-11-05 14:00:00', $utc),
            'Pixel',
            'cat',
            'Sophie',
            'Martin',
            '+33612345678',
            new \DateTimeImmutable('2026-10-28 10:00:00', $utc),
        );
        $request->decline(DeclineReason::EXPIRED, new \DateTimeImmutable('2026-10-29 08:00:00', $utc));
        $this->bookingRequests->save($request);

        $files = $this->unzip($this->builder()->build(W::USER));

        self::assertSame([[
            'clinic'        => ['name' => 'Clinique du Port', 'city' => 'Fort-de-France'],
            'pet'           => ['name' => 'Pixel', 'species' => 'cat'],
            'motif'         => 'Première consultation',
            'startAt'       => '2026-11-05T10:00:00-04:00',
            'status'        => 'declined',
            'declineReason' => 'expired',
            'createdAt'     => '2026-10-28T06:00:00-04:00',
        ]], self::decode($files['account.json'])['bookingRequests'] ?? null);
        self::assertCount(1, $this->pdf->rendered);
    }

    public function testAnAccountNoLongerActiveIsNotExported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active owner account "' . W::USER . '" to export.');

        $this->fakes->accounts = [];
        $this->builder()->build(W::USER);
    }

    public static function appointment(string $id, ?string $animalId, string $status, ?string $motifLabel = null, ?string $owner = W::CLIENT): OwnerAppointment
    {
        $utc = new \DateTimeZone('UTC');

        return new OwnerAppointment($id, $owner, $animalId, self::VET, new \DateTimeImmutable('2026-11-05 09:00:00', $utc), 30, $status, true, false, 'clinic', null, $motifLabel, null, new \DateTimeImmutable('2026-10-01 08:00:00', $utc), null);
    }

    private function builder(): DataExportBuilder
    {
        $appointments = new class(function (string $page): void {
            $this->pages[] = $page;
        }) implements OwnerAppointmentsInterface {
            /** @param \Closure(string): void $record */
            public function __construct(private readonly \Closure $record)
            {
            }

            public function nextFor(string $clinicId, array $animalIds, \DateTimeImmutable $nowUtc): ?UpcomingAppointment
            {
                return null;
            }

            public function page(string $clinicId, array $ownerIds, array $animalIds, AppointmentScope $scope, ?string $cursor, ?int $limit, \DateTimeImmutable $nowUtc): CursorPage
            {
                ($this->record)($scope->value . ':' . ($cursor ?? '-'));

                return match ($scope->value . ':' . ($cursor ?? '-')) {
                    'upcoming:-' => new CursorPage([DataExportBuilderTest::appointment(DataExportBuilderTest::UPCOMING, W::PET, 'confirmed', 'Rappel vaccin')], null),
                    'past:-'     => new CursorPage([DataExportBuilderTest::appointment(DataExportBuilderTest::PAST, null, 'completed'), DataExportBuilderTest::appointment(DataExportBuilderTest::FOREIGN, W::PET3, 'completed', owner: W::CLIENT2)], 'p2'),
                    default      => new CursorPage([DataExportBuilderTest::appointment(DataExportBuilderTest::PAST_2, W::PET, 'no_show')], null),
                };
            }

            public function find(string $clinicId, string $appointmentId): ?OwnerAppointment
            {
                return null;
            }

            public function careBookings(string $clinicId, array $animalIds, \DateTimeImmutable $fromUtc): array
            {
                return [];
            }
        };

        return new DataExportBuilder(
            $this->fakes,
            $this->profiles,
            $this->world->documents->links,
            $this->world->documents->fakes,
            $this->world->ownerPets(),
            $appointments,
            new PortalAppointments($this->world->careItems(), $this->world->fakes, $this->world->documents->fakes, $this->questionnaires),
            $this->world->model,
            $this->fakes,
            $this->changes,
            $this->renewals,
            $this->questionnaires,
            $this->bookingRequests,
            $this->pdf,
            $this->world->documents->clock,
        );
    }

    private function link(string $clinicId, string $clientId, ClientLinkStatus $status, string $linkedAt, ?string $revokedAt): void
    {
        $utc = new \DateTimeZone('UTC');
        $this->world->documents->links->save(ClientLink::reconstitute(
            ClientLinkId::fromString($this->world->documents->fakes->generate()),
            W::USER,
            $clinicId,
            $clientId,
            $status,
            new \DateTimeImmutable($linkedAt, $utc),
            null === $revokedAt ? null : new \DateTimeImmutable($revokedAt, $utc),
        ));
    }

    /**
     * @return array<string, string> name → content, in archive order
     */
    private function unzip(string $archive): array
    {
        $path = tempnam(sys_get_temp_dir(), 'kiveto-test-');
        self::assertIsString($path);
        file_put_contents($path, $archive);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            $files = [];

            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = $zip->getNameIndex($i);
                self::assertIsString($name);
                $files[$name] = (string) $zip->getFromIndex($i);
            }
            $zip->close();

            return $files;
        } finally {
            unlink($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $json): array
    {
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        $fields = [];
        foreach ($data as $key => $value) {
            $fields[(string) $key] = $value;
        }

        return $fields;
    }
}
