<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClientPortal\Story;

use App\Context\Billing\Application\Command\IssueCreditNote\IssueCreditNote;
use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Context\ClientPortal\Application\Command\StorePublishedDocumentFile\StorePublishedDocumentFileHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalDocumentReadRepository;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPublishedDocumentRepository;
use App\Fixtures\Context\Client\Story\ClientSheetStatesStory;
use App\Fixtures\Context\ClientPortal\Factory\ClientLinkEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\OwnerProfileEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\PortalInvitationEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Fixtures\System\IdentityAccess\Story\PortalUserStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The documents the Paris clinic published on the Portal (registry rows,
 * with a small real PDF stored under each served version, so every demo
 * download works). Sources are the real invoices, ordonnances and closed
 * consultations of the clinic dataset; a few invoices are written here for
 * the states the dataset has no example of. The rows the publication
 * pipeline already wrote (awaiting_link) are served for the clients linked
 * here, as their link confirmation would have done.
 *
 * | Owner (account)                                    | Documents                                                                 |
 * |----------------------------------------------------|---------------------------------------------------------------------------|
 * | Sophie Garnier (+33612345679, proprietaire@…)       | > 20: invoices paid / unpaid, ordonnances, comptes rendus (one re-shared, |
 * |                                                    | v2), one invoice without pet, the 3 newest unread; plus a report waiting  |
 * |                                                    | for its file (pending_file), a failed one and a withdrawn invoice (hidden) |
 * | Thierry Marchand (+33612345680)                    | 2: a renewable and an expired ordonnance (one read)                       |
 * | Sophie Rey (+33612345681)                          | partially paid, credited, an invoice she paid for Oscar (Garnier), and   |
 * |                                                    | what the pipeline had published while she had no account                 |
 * | Julien Girard (+33612345678, the demo account)     | Paris: 2 paid invoices for Oslo · Lyon: none                              |
 * | Paolo Costa (no Portal account)                    | 1 unpaid invoice awaiting his link (awaiting_link, nothing rendered)      |
 * | Élise Moreau (revoked link)                        | 1 paid invoice, never shown since her link was withdrawn                  |
 */
final class PortalDocumentsStory extends Story
{
    public const string REY_PHONE      = '+33612345681';
    public const string MARCHAND_PHONE = '+33612345680';

    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string TZ    = 'Europe/Paris';

    /** The newest documents of an account stay unread. */
    private const int UNREAD = 3;

    private \DateTimeImmutable $now;

    public function __construct(
        private readonly Connection $connection,
        private readonly DbalPublishedDocumentRepository $documents,
        private readonly DbalDocumentReadRepository $reads,
        private readonly FileStorageInterface $files,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly InvoiceNumberGeneratorInterface $numbers,
        private readonly SellerIdentityProviderInterface $sellers,
        private readonly CommandBusInterface $commandBus,
        private readonly ClockInterface $clock,
        #[Autowire('%env(PORTAL_TERMS_VERSION)%')]
        private readonly string $termsVersion,
    ) {
    }

    public function build(): void
    {
        ClientPortalStatesStory::load();

        $this->now = $this->clock->now();

        $this->busyOwner();
        $this->twoOrdonnances();
        $this->payerOfAnotherPet();
        $this->demoAccount();
        $this->awaitingLink();
        $this->revokedLink();
    }

    /**
     * A one-page PDF (Helvetica, WinAnsi) with a title and a few lines.
     *
     * @param list<string> $lines
     */
    public static function pdf(string $title, array $lines): string
    {
        $text = \sprintf("BT /F1 18 Tf 56 770 Td (%s) Tj ET\n", self::pdfText($title));
        $y    = 735;

        foreach ($lines as $line) {
            $text .= \sprintf("BT /F1 11 Tf 56 %d Td (%s) Tj ET\n", $y, self::pdfText($line));
            $y -= 20;
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length ' . \strlen($text) . " >>\nstream\n" . $text . 'endstream',
        ];

        $pdf     = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = \strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = \strlen($pdf);
        $pdf .= "xref\n0 " . (\count($objects) + 1) . "\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= \sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . 'trailer << /Size ' . (\count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }

    /** Sophie Garnier (« busy » client sheet): every kind of document, more than 20. */
    private function busyOwner(): void
    {
        $client = ClientSheetStatesStory::CLIENT_BUSY_ID;
        $userId = $this->portalUserId(PortalUserStory::DEMO_PHONE_WITH_EMAIL);
        $this->account($userId, $client, 'Sophie', 'Garnier', '-40 days');

        $listed = [];

        foreach ($this->invoicesOf($client) as $i => $invoice) {
            // The oldest invoice: its visit was never tied to an identified animal.
            $listed[] = $this->publish(DocumentType::INVOICE, $client, $invoice['id'], 'Facture ' . $invoice['number'], $invoice['issuedAt'], 0 === $i ? null : $invoice['animal'], 0 === $i ? null : $invoice['patient'], null, null);
        }

        foreach ($this->prescriptionsOf($client) as $prescription) {
            $listed[] = $this->publish(DocumentType::PRESCRIPTION, $client, $prescription['id'], 'Ordonnance ' . $prescription['number'], $prescription['issuedAt'], $prescription['animal'], null, $prescription['consultation'], $prescription['prescriber']);
        }

        $reports = $this->closedConsultationsOf($client);
        $last    = \count($reports) - 1;

        foreach ($reports as $i => $report) {
            $title = 'Compte rendu du ' . $report['startedAt']->setTimezone(new \DateTimeZone(self::TZ))->format('d/m/Y');
            $state = match ($i) {
                $last     => 'reshared',
                $last - 1 => PublicationStatus::PENDING_FILE,
                $last - 2 => PublicationStatus::FAILED,
                default   => PublicationStatus::AVAILABLE,
            };

            if ($state instanceof PublicationStatus && PublicationStatus::AVAILABLE !== $state) {
                // Shared, but no file yet: the render is running (or gave up after five attempts).
                $this->publish(DocumentType::REPORT, $client, $report['id'], $title, $report['closedAt'], $report['animal'], $report['patient'], $report['id'], $report['practitioner'], $state);

                continue;
            }

            $listed[] = $this->publish(DocumentType::REPORT, $client, $report['id'], $title, $report['closedAt'], $report['animal'], $report['patient'], $report['id'], $report['practitioner'], version: 'reshared' === $state ? 2 : 1);
        }

        // Cancelled at the desk after it was published: withdrawn, never shown again.
        $cancelled = $this->invoice($client, 'Sophie Garnier', 'Consultation de suivi — Oscar', 3500, '-12 days 18:10', paid: false, cancelled: true);
        $this->publish(DocumentType::INVOICE, $client, $cancelled['id'], 'Facture ' . $cancelled['number'], $cancelled['issuedAt'], $this->animalOf($client, 'Oscar'), null, null, null, PublicationStatus::WITHDRAWN);

        $this->markRead($userId, [...$listed, ...$this->releaseAwaiting($client)]);
    }

    /** Thierry Marchand (« normal » client sheet): exactly two ordonnances, one past its validity. */
    private function twoOrdonnances(): void
    {
        $client = ClientSheetStatesStory::CLIENT_NORMAL_ID;
        $userId = PortalUserFactory::new()->withPhone(self::MARCHAND_PHONE)->create()->getId()->toRfc4122();
        $this->account($userId, $client, 'Thierry', 'Marchand', '-200 days');

        $listed = [];

        foreach (\array_slice($this->prescriptionsOf($client), 0, 2) as $prescription) {
            $listed[] = $this->publish(DocumentType::PRESCRIPTION, $client, $prescription['id'], 'Ordonnance ' . $prescription['number'], $prescription['issuedAt'], $prescription['animal'], null, $prescription['consultation'], $prescription['prescriber']);
        }

        $this->markRead($userId, [...$listed, ...$this->releaseAwaiting($client)], unread: 1);
    }

    /**
     * Sophie Rey: her invoice paid in part (DirectoryBalancesStory), one
     * entirely credited, and one she paid for Oscar, Sophie Garnier's dog —
     * the payer sees it, the owner does not.
     */
    private function payerOfAnotherPet(): void
    {
        $client = $this->clientId('Sophie', 'Rey');
        $userId = PortalUserFactory::new()->withPhone(self::REY_PHONE)->create()->getId()->toRfc4122();
        $this->account($userId, $client, 'Sophie', 'Rey', '-30 days');

        $listed = [];

        foreach ($this->invoicesOf($client, 'PARTIALLY_PAID') as $invoice) {
            $listed[] = $this->publish(DocumentType::INVOICE, $client, $invoice['id'], 'Facture ' . $invoice['number'], $invoice['issuedAt'], $invoice['animal'], $invoice['patient'], null, null);
        }

        $credited = $this->invoice($client, 'Sophie Rey', 'Bilan sanguin complet', 8800, '-15 days 10:40', paid: false, credited: true);
        $listed[] = $this->publish(DocumentType::INVOICE, $client, $credited['id'], 'Facture ' . $credited['number'], $credited['issuedAt'], null, null, null, null);

        $forOscar = $this->invoice($client, 'Sophie Rey', 'Consultation de suivi — Oscar (Garnier)', 3500, '-5 days 17:20', paid: true);
        $listed[] = $this->publish(DocumentType::INVOICE, $client, $forOscar['id'], 'Facture ' . $forOscar['number'], $forOscar['issuedAt'], $this->animalOf(ClientSheetStatesStory::CLIENT_BUSY_ID, 'Oscar'), null, null, null);

        $this->markRead($userId, [...$listed, ...$this->releaseAwaiting($client)], unread: 2);
    }

    /** Julien Girard, the demo account: two paid invoices for Oslo in Paris, nothing in Lyon. */
    private function demoAccount(): void
    {
        $client = $this->clientId('Julien', 'Girard', PortalUserStory::DEMO_PHONE);
        $userId = $this->portalUserId(PortalUserStory::DEMO_PHONE);
        $oslo   = $this->animalOf($client, 'Oslo');
        $listed = [];

        foreach ([['Vaccination CHPL + rage — Oslo', 7800, '-58 days 11:15'], ['Consultation — otite externe, Oslo', 5200, '-9 days 09:40']] as [$label, $gross, $at]) {
            $invoice  = $this->invoice($client, 'Julien Girard', $label, $gross, $at, paid: true);
            $listed[] = $this->publish(DocumentType::INVOICE, $client, $invoice['id'], 'Facture ' . $invoice['number'], $invoice['issuedAt'], $oslo, null, null, null);
        }

        $this->markRead($userId, [...$listed, ...$this->releaseAwaiting($client)], unread: 1);
    }

    /** Paolo Costa has no Portal account: his invoice waits for his link, nothing is rendered. */
    private function awaitingLink(): void
    {
        $client = $this->clientId('Paolo', 'Costa');

        foreach ($this->invoicesOf($client, 'ISSUED') as $invoice) {
            $this->publish(DocumentType::INVOICE, $client, $invoice['id'], 'Facture ' . $invoice['number'], $invoice['issuedAt'], $invoice['animal'], $invoice['patient'], null, null, PublicationStatus::AWAITING_LINK);
        }
    }

    /** Élise Moreau's link was withdrawn with her file: her document is never shown again. */
    private function revokedLink(): void
    {
        $client  = ClientPortalStatesStory::MOREAU_ID;
        $invoice = $this->invoice($client, 'Élise Moreau', 'Consultation et vaccination', 6400, '-75 days 15:30', paid: true);
        $this->publish(DocumentType::INVOICE, $client, $invoice['id'], 'Facture ' . $invoice['number'], $invoice['issuedAt'], null, null, null, null);
    }

    /**
     * One registry row. A served row (available, or a share being rendered
     * again) gets a PDF for each of its versions.
     *
     * @return array{id: string, issuedAt: \DateTimeImmutable}
     */
    private function publish(
        DocumentType $type,
        string $clientId,
        string $sourceRef,
        string $title,
        \DateTimeImmutable $issuedAt,
        ?string $animalId,
        ?string $patientId,
        ?string $consultationId,
        ?string $practitioner,
        PublicationStatus $status = PublicationStatus::AVAILABLE,
        int $version = 1,
    ): array {
        // This row wins over one the publication pipeline already wrote for the source.
        $source = [$this->binary(self::PARIS), $type->value, $this->binary($sourceRef)];
        $this->connection->executeStatement(
            'DELETE r FROM ' . DbalDocumentReadRepository::TABLE . ' r JOIN ' . DbalPublishedDocumentRepository::TABLE
            . ' d ON d.id = r.document_id WHERE d.clinic_id = ? AND d.type = ? AND d.source_ref = ?',
            $source,
        );
        $this->connection->executeStatement('DELETE FROM ' . DbalPublishedDocumentRepository::TABLE . ' WHERE clinic_id = ? AND type = ? AND source_ref = ?', $source);

        $id          = Uuid::v7()->toRfc4122();
        $publishedAt = min($this->now, $issuedAt->modify('+1 hour'));
        $served      = PublicationStatus::AVAILABLE === $status ? $version : 0;
        $updatedAt   = $served > 1 ? min($this->now, $publishedAt->modify('+3 days')) : $publishedAt;

        if (PublicationStatus::WITHDRAWN === $status) {
            $served    = 1;
            $updatedAt = min($this->now, $publishedAt->modify('+2 days'));
        }

        $pending = match ($status) {
            PublicationStatus::PENDING_FILE, PublicationStatus::FAILED => 1,
            default => null,
        };

        $this->documents->save(PublishedDocument::reconstitute(
            id: PublishedDocumentId::fromString($id),
            clinicId: self::PARIS,
            clientId: $clientId,
            type: $type,
            sourceRef: $sourceRef,
            consultationId: $consultationId,
            patientId: $patientId,
            petIds: null === $animalId ? [] : [$animalId],
            title: $title,
            issuedAt: $issuedAt,
            practitionerName: $practitioner,
            storageKey: $served > 0 ? StorePublishedDocumentFileHandler::storageKey($id, $served) : null,
            fileVersion: $served,
            pendingFileVersion: $pending,
            renderAttempts: PublicationStatus::FAILED === $status ? PublishedDocument::MAX_RENDER_ATTEMPTS : 0,
            status: $status,
            publishedAt: $publishedAt,
            updatedAt: $updatedAt,
        ));

        if (PublicationStatus::AVAILABLE === $status) {
            for ($v = 1; $v <= $served; ++$v) {
                $this->files->storeAt(StorePublishedDocumentFileHandler::storageKey($id, $v), self::pdf($title, [
                    'Clinique vétérinaire de Paris',
                    'Émis le ' . $issuedAt->setTimezone(new \DateTimeZone(self::TZ))->format('d/m/Y à H:i'),
                    null === $practitioner ? '' : 'Praticien : ' . $practitioner,
                    $v > 1 ? \sprintf('Version %d — partagé à nouveau après un avenant', $v) : 'Document de démonstration Kiveto',
                ]));
            }
        }

        return ['id' => $id, 'issuedAt' => $issuedAt];
    }

    /**
     * The documents the publication pipeline registered for the client while
     * they had no account: their link is confirmed now, so they are served
     * (as ClientLinkConfirmed and the render would have done).
     *
     * @return list<array{id: string, issuedAt: \DateTimeImmutable}>
     */
    private function releaseAwaiting(string $clientId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(id) AS id, title, issued_at FROM ' . DbalPublishedDocumentRepository::TABLE
            . ' WHERE clinic_id = ? AND client_id = ? AND status = ?',
            [$this->binary(self::PARIS), $this->binary($clientId), PublicationStatus::AWAITING_LINK->value],
        );
        $released = [];

        foreach ($rows as $row) {
            $id       = RowAccessor::string($row, 'id');
            $title    = RowAccessor::string($row, 'title');
            $issuedAt = self::utc(RowAccessor::string($row, 'issued_at'));
            $key      = StorePublishedDocumentFileHandler::storageKey($id, 1);

            $this->files->storeAt($key, self::pdf($title, [
                'Clinique vétérinaire de Paris',
                'Émis le ' . $issuedAt->setTimezone(new \DateTimeZone(self::TZ))->format('d/m/Y à H:i'),
                'Document de démonstration Kiveto',
            ]));
            $this->connection->executeStatement(
                'UPDATE ' . DbalPublishedDocumentRepository::TABLE
                . ' SET status = ?, file_version = 1, pending_file_version = NULL, storage_key = ?, updated_at = ? WHERE id = ?',
                [PublicationStatus::AVAILABLE->value, $key, $this->now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $this->binary($id)],
            );
            $released[] = ['id' => $id, 'issuedAt' => $issuedAt];
        }

        return $released;
    }

    /**
     * The account has opened everything but its newest documents.
     *
     * @param list<array{id: string, issuedAt: \DateTimeImmutable}> $listed
     */
    private function markRead(string $userId, array $listed, int $unread = self::UNREAD): void
    {
        usort($listed, static fn (array $a, array $b): int => $a['issuedAt'] <=> $b['issuedAt']);

        foreach (\array_slice($listed, 0, max(0, \count($listed) - $unread)) as $document) {
            $this->reads->markRead($document['id'], $userId, min($this->now, $document['issuedAt']->modify('+1 day')));
        }
    }

    /** A confirmed link reached through an accepted invitation, and the owner's profile. */
    private function account(string $userId, string $clientId, string $firstName, string $lastName, string $since): void
    {
        $linkedAt = $this->now->modify($since);
        $phone    = $this->connection->fetchOne('SELECT phone FROM identity_access__users WHERE id = ?', [Uuid::fromString($userId)->toBinary()]);
        \assert(\is_string($phone));

        PortalInvitationEntityFactory::new()
            ->forClient(self::PARIS, $clientId, $phone, null)
            ->sentAt($linkedAt->modify('-1 day'))
            ->acceptedBy($userId, $linkedAt)
            ->create()
        ;
        ClientLinkEntityFactory::new()->between($userId, self::PARIS, $clientId)->linkedAt($linkedAt)->create();
        OwnerProfileEntityFactory::new()->forUser($userId, $firstName, $lastName)->acceptedTerms($this->termsVersion, $linkedAt)->create();
    }

    /**
     * An invoice written for the demo, dated in the past: paid by card,
     * left unpaid, cancelled, or entirely credited.
     *
     * @return array{id: string, number: string, issuedAt: \DateTimeImmutable}
     */
    private function invoice(string $clientId, string $payerName, string $label, int $gross, string $when, bool $paid, bool $cancelled = false, bool $credited = false): array
    {
        $clinic = ClinicId::fromString(self::PARIS);
        $eur    = CurrencyCode::fromString('EUR');
        $at     = $this->now->setTimezone(new \DateTimeZone(self::TZ))->modify($when);
        $id     = Uuid::v7()->toRfc4122();
        $lineId = Uuid::v7()->toRfc4122();

        $invoice = Invoice::draft(
            id: InvoiceId::fromString($id),
            clinicId: $clinic,
            admissionId: null,
            payer: Payer::create(PayerId::fromString($clientId), $payerName, ['Paris']),
            currency: $eur,
            now: $at,
        );
        $invoice->addLine(InvoiceLine::create(
            id: InvoiceLineId::fromString($lineId),
            label: $label,
            code: 'CONS_STD',
            quantity: '1',
            enteredUnitPrice: Money::fromMinorUnits($gross, $eur),
            priceMode: PriceMode::INCLUDING_TAX,
            taxCategoryCode: 'veterinary.act.consultation',
            taxRatePercent: '20',
            operationCategory: OperationCategory::SERVICES,
            source: InvoiceLineSource::MANUAL,
            sourceReference: null,
        ), $at);
        $number = $this->numbers->next($clinic, (int) $at->format('Y'));
        $invoice->issue($number, $this->sellers->forClinic(self::PARIS), $at);

        if ($paid) {
            $invoice->recordPayment(Payment::create(
                id: PaymentId::fromString(Uuid::v7()->toRfc4122()),
                method: PaymentMethod::CARD,
                amount: Money::fromMinorUnits($gross, $eur),
                receivedAt: $at,
                reference: null,
            ), $at);
        }

        if ($cancelled) {
            $invoice->cancel('Facturée deux fois par erreur', $at->modify('+1 day'));
        }

        $this->invoices->save($invoice);

        if ($credited) {
            $this->commandBus->dispatch(new IssueCreditNote(self::PARIS, $id, [$lineId], 'Analyse non réalisée : prélèvement insuffisant'));
        }

        return ['id' => $id, 'number' => $number->toString(), 'issuedAt' => $at->setTimezone(new \DateTimeZone('UTC'))];
    }

    /**
     * The issued invoices of a payer, oldest first, with the animal of their visit.
     *
     * @return list<array{id: string, number: string, issuedAt: \DateTimeImmutable, animal: ?string, patient: ?string}>
     */
    private function invoicesOf(string $clientId, ?string $status = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(i.id) AS id, i.number, i.issued_at, BIN_TO_UUID(p.animal_link_id) AS animal, BIN_TO_UUID(p.id) AS patient
             FROM billing__invoices i
             LEFT JOIN admission__admissions a ON a.id = i.admission_id
             LEFT JOIN patient__patients p ON p.id = a.patient_id
             WHERE i.clinic_id = :clinic AND i.payer_client_id = :client AND i.number IS NOT NULL
               AND i.status IN ('ISSUED', 'PARTIALLY_PAID', 'PAID', 'CREDITED') AND (:status IS NULL OR i.status = :status)
             ORDER BY i.issued_at ASC, i.id ASC",
            ['clinic' => $this->binary(self::PARIS), 'client' => $this->binary($clientId), 'status' => $status],
        );

        return array_map(static fn (array $row): array => [
            'id'       => RowAccessor::string($row, 'id'),
            'number'   => RowAccessor::string($row, 'number'),
            'issuedAt' => self::utc(RowAccessor::string($row, 'issued_at')),
            'animal'   => RowAccessor::nullableString($row, 'animal'),
            'patient'  => RowAccessor::nullableString($row, 'patient'),
        ], $rows);
    }

    /**
     * The signed ordonnances written for a client, oldest first.
     *
     * @return list<array{id: string, number: string, issuedAt: \DateTimeImmutable, animal: ?string, consultation: string, prescriber: string}>
     */
    private function prescriptionsOf(string $clientId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(id) AS id, number, issued_at, BIN_TO_UUID(patient_animal_id) AS animal, BIN_TO_UUID(consultation_id) AS consultation, prescriber_display_name
             FROM prescription__prescriptions
             WHERE clinic_id = :clinic AND owner_client_id = :client AND status = 'ISSUED' AND number IS NOT NULL
             ORDER BY issued_at ASC, id ASC",
            ['clinic' => $this->binary(self::PARIS), 'client' => $this->binary($clientId)],
        );

        return array_map(static fn (array $row): array => [
            'id'           => RowAccessor::string($row, 'id'),
            'number'       => RowAccessor::string($row, 'number'),
            'issuedAt'     => self::utc(RowAccessor::string($row, 'issued_at')),
            'animal'       => RowAccessor::nullableString($row, 'animal'),
            'consultation' => RowAccessor::string($row, 'consultation'),
            'prescriber'   => RowAccessor::string($row, 'prescriber_display_name'),
        ], $rows);
    }

    /**
     * The closed consultations of the animals a client is the primary owner of, oldest first.
     *
     * @return list<array{id: string, startedAt: \DateTimeImmutable, closedAt: \DateTimeImmutable, animal: string, patient: string, practitioner: ?string}>
     */
    private function closedConsultationsOf(string $clientId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(c.id) AS id, c.started_at_utc, c.closed_at_utc, BIN_TO_UUID(p.animal_link_id) AS animal, BIN_TO_UUID(p.id) AS patient,
                    (SELECT CONCAT('Dr ', s.first_name, ' ', s.last_name) FROM clinic__clinic_memberships m
                       JOIN clinic__staff_profiles s ON s.membership_id = m.id
                      WHERE m.user_id = c.practitioner_user_id AND m.clinic_id = c.clinic_id LIMIT 1) AS practitioner
             FROM consultation__consultations c
             JOIN patient__patients p ON p.id = c.patient_id
             JOIN animal__ownerships o ON o.animal_id = p.animal_link_id AND o.role = 'primary' AND o.status = 'active'
             WHERE c.clinic_id = :clinic AND o.client_id = :client AND c.status = 'CLOSED' AND c.closed_at_utc IS NOT NULL
             ORDER BY c.closed_at_utc ASC, c.id ASC",
            ['clinic' => $this->binary(self::PARIS), 'client' => $this->binary($clientId)],
        );

        return array_map(static fn (array $row): array => [
            'id'           => RowAccessor::string($row, 'id'),
            'startedAt'    => self::utc(RowAccessor::string($row, 'started_at_utc')),
            'closedAt'     => self::utc(RowAccessor::string($row, 'closed_at_utc')),
            'animal'       => RowAccessor::string($row, 'animal'),
            'patient'      => RowAccessor::string($row, 'patient'),
            'practitioner' => RowAccessor::nullableString($row, 'practitioner'),
        ], $rows);
    }

    private function animalOf(string $clientId, string $name): string
    {
        $id = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(a.id) FROM animal__animals a JOIN animal__ownerships o ON o.animal_id = a.id AND o.role = 'primary'
             WHERE o.client_id = ? AND a.name = ? LIMIT 1",
            [$this->binary($clientId), $name],
        );
        \assert(\is_string($id), \sprintf('The dataset has no animal "%s" for client %s.', $name, $clientId));

        return $id;
    }

    private function clientId(string $firstName, string $lastName, ?string $phone = null): string
    {
        $id = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(c.id) FROM client__clients c
             WHERE c.clinic_id = ? AND c.first_name = ? AND c.last_name = ?
               AND (? IS NULL OR EXISTS (SELECT 1 FROM client__contact_methods m WHERE m.client_id = c.id AND m.value = ?))
             ORDER BY c.id LIMIT 1',
            [$this->binary(self::PARIS), $firstName, $lastName, $phone, $phone],
        );
        \assert(\is_string($id), \sprintf('The dataset has no client %s %s in Paris.', $firstName, $lastName));

        return $id;
    }

    private function portalUserId(string $phone): string
    {
        $id = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE type = 'PORTAL' AND phone = ?", [$phone]);
        \assert(\is_string($id), 'PortalUserStory creates the demo owner accounts.');

        return $id;
    }

    private function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }

    private static function pdfText(string $utf8): string
    {
        $latin = mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');

        return strtr($latin, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }
}
