<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Export;

use App\Context\ClientPortal\Application\Port\AppointmentScope;
use App\Context\ClientPortal\Application\Port\ClientConsentsInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\DataExportBuilderInterface;
use App\Context\ClientPortal\Application\Port\DocumentPosition;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\OwnerAccount;
use App\Context\ClientPortal\Application\Port\OwnerAccountsInterface;
use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Application\Port\OwnerAppointmentsInterface;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\PortalAppointment;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use App\Context\ClientPortal\Application\Service\PortalAppointments;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentPdfRendererInterface;
use App\Shared\Domain\Time\ClockInterface;

/**
 * The archive of a data export, canonical content `schemaVersion: 1`:
 *
 * - `account.json`: phone, e-mail, profile, terms acceptance;
 * - `clinics/<clinicId>.json`, one per clinic the account was ever linked
 *   to: the clinic, the links' status and dates and, through the confirmed
 *   links only, the consents and their history, the appointments
 *   `{startAt, pet, motif, status}` and the published documents
 *   `{type, title, issuedAt}` (no file);
 * - `summary.pdf`: the same, readable.
 *
 * Account instants are UTC; a clinic's instants are in its time zone. The
 * archive is built in a temporary file, removed whatever happens.
 */
final readonly class DataExportBuilder implements DataExportBuilderInterface
{
    public const int SCHEMA_VERSION = 1;

    public const string SUMMARY_TEMPLATE = 'portal/export/summary.html.twig';

    private const int PAGE = 100;

    private const string SUMMARY_TIME_ZONE = 'Europe/Paris';

    public function __construct(
        private OwnerAccountsInterface $accounts,
        private OwnerProfileRepositoryInterface $profiles,
        private ClientLinkRepositoryInterface $links,
        private ClinicProfileInterface $clinics,
        private OwnerPets $pets,
        private OwnerAppointmentsInterface $appointments,
        private PortalAppointments $appointmentViews,
        private PublishedDocumentReadModelInterface $documents,
        private ClientConsentsInterface $consents,
        private DocumentPdfRendererInterface $pdf,
        private ClockInterface $clock,
    ) {
    }

    public function build(string $userId): string
    {
        $account  = $this->accounts->accountOf($userId) ?? throw new \RuntimeException(\sprintf('No active owner account "%s" to export.', $userId));
        $now      = $this->clock->now();
        $byClinic = [];

        foreach ($this->links->findByUser($userId) as $link) {
            $byClinic[$link->clinicId()][] = $link;
        }

        $clinics = [];
        foreach ($byClinic as $clinicId => $links) {
            $clinics[(string) $clinicId] = $this->clinic($userId, (string) $clinicId, $links, $now);
        }

        $accountFile = $this->account($account, $this->profiles->findByUserId($userId), array_keys($clinics), $now);
        $files       = ['account.json' => self::json($accountFile)];

        foreach ($clinics as $clinicId => $clinic) {
            $files['clinics/' . $clinicId . '.json'] = self::json($clinic);
        }

        $files['summary.pdf'] = $this->pdf->render(
            self::SUMMARY_TEMPLATE,
            [
                'account'     => $accountFile,
                'clinics'     => array_values($clinics),
                'generatedAt' => $now->setTimezone(new \DateTimeZone(self::SUMMARY_TIME_ZONE)),
            ],
            new DocumentFooter(null, 'Export de vos données'),
        );

        return self::zip($files);
    }

    /**
     * @param list<string> $clinicIds
     *
     * @return array<string, mixed>
     */
    private function account(OwnerAccount $account, ?OwnerProfile $profile, array $clinicIds, \DateTimeImmutable $now): array
    {
        $address = $profile?->address();

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'generatedAt'   => self::instant($now, 'UTC'),
            'account'       => [
                'id'            => $account->userId,
                'phone'         => $account->phone,
                'email'         => $account->email,
                'emailVerified' => $account->emailVerified,
            ],
            'profile' => null === $profile ? null : [
                'firstName' => $profile->firstName(),
                'lastName'  => $profile->lastName(),
                'address'   => null === $address ? null : [
                    'streetLine1' => $address->streetLine1,
                    'streetLine2' => $address->streetLine2,
                    'postalCode'  => $address->postalCode,
                    'city'        => $address->city,
                    'countryCode' => $address->countryCode,
                ],
            ],
            'terms' => null === $profile ? null : [
                'version'    => $profile->termsVersion(),
                'acceptedAt' => self::instant($profile->termsAcceptedAt(), 'UTC'),
            ],
            'clinics' => $clinicIds,
        ];
    }

    /**
     * @param list<ClientLink> $links
     *
     * @return array<string, mixed>
     */
    private function clinic(string $userId, string $clinicId, array $links, \DateTimeImmutable $now): array
    {
        $label     = $this->clinics->clinicOf($clinicId);
        $zone      = $label->timeZone;
        $clientIds = $this->pets->clientIds($userId, $clinicId);
        $consents  = [] === $clientIds ? ['consents' => [], 'history' => []] : $this->consents->exportOf($clinicId, $clientIds);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'clinic'        => ['id' => $clinicId, 'name' => $label->name, 'city' => $label->city],
            'links'         => array_map(static fn (ClientLink $link): array => [
                'status'    => $link->status()->value,
                'linkedAt'  => self::nullableInstant($link->linkedAt(), $zone),
                'revokedAt' => self::nullableInstant($link->revokedAt(), $zone),
            ], $links),
            'consents'       => $consents['consents'],
            'consentHistory' => array_map(static fn (array $change): array => [
                'kind'    => $change['kind'],
                'channel' => $change['channel'],
                'action'  => $change['action'],
                'source'  => $change['source'],
                'at'      => self::instant($change['at'], $zone),
            ], $consents['history']),
            'appointments' => [] === $clientIds ? [] : $this->appointmentsOf($userId, $clinicId, $clientIds, $now),
            'documents'    => [] === $clientIds ? [] : $this->documentsOf($userId, $clinicId, $zone),
        ];
    }

    /**
     * Every appointment of the account in the clinic, upcoming then past.
     *
     * @param list<string> $clientIds
     *
     * @return list<array{startAt: string, pet: ?string, motif: string, status: string}>
     */
    private function appointmentsOf(string $userId, string $clinicId, array $clientIds, \DateTimeImmutable $now): array
    {
        $pets   = $this->pets->visible($userId, $clinicId);
        $petIds = array_map(static fn (PortalPet $pet): string => $pet->id, $pets);
        $found  = [];

        foreach ([AppointmentScope::UPCOMING, AppointmentScope::PAST] as $scope) {
            $cursor = null;

            do {
                $page   = $this->appointments->page($clinicId, $clientIds, $petIds, $scope, $cursor, self::PAGE, $now);
                $found  = [...$found, ...array_values(array_filter($page->items, static fn (OwnerAppointment $a): bool => PortalAppointments::belongsTo($a, $clientIds, $pets)))];
                $cursor = $page->nextCursor;
            } while (null !== $cursor);
        }

        return array_map(static fn (PortalAppointment $appointment): array => [
            'startAt' => $appointment->startAt->format(\DATE_ATOM),
            'pet'     => $appointment->petName,
            'motif'   => $appointment->motifLabel,
            'status'  => $appointment->status,
        ], $this->appointmentViews->views($clinicId, $found, $pets));
    }

    /**
     * Every listable document of the account in the clinic, latest first.
     *
     * @return list<array{type: string, title: string, issuedAt: string}>
     */
    private function documentsOf(string $userId, string $clinicId, string $zone): array
    {
        $documents = [];
        $after     = null;

        do {
            $page      = $this->documents->listable($userId, $clinicId, null, null, $after, self::PAGE);
            $documents = [...$documents, ...$page];
            $last      = end($page);
            $after     = false === $last ? null : new DocumentPosition($last->issuedAt, $last->id);
        } while (self::PAGE === \count($page));

        return array_map(static fn (ListedDocument $document): array => [
            'type'     => $document->type,
            'title'    => $document->title,
            'issuedAt' => self::instant($document->issuedAt, $zone),
        ], $documents);
    }

    /**
     * @param array<string, string> $files name → content
     */
    private static function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kiveto-export-') ?: throw new \RuntimeException('No temporary file for the data export.');

        try {
            $zip = new \ZipArchive();
            true === $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) || throw new \RuntimeException('The data export archive could not be opened.');

            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }

            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function json(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . "\n";
    }

    private static function instant(\DateTimeImmutable $at, string $zone): string
    {
        return $at->setTimezone(new \DateTimeZone($zone))->format(\DATE_ATOM);
    }

    private static function nullableInstant(?\DateTimeImmutable $at, string $zone): ?string
    {
        return null === $at ? null : self::instant($at, $zone);
    }
}
