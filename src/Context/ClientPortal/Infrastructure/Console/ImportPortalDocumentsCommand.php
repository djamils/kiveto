<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Console;

use App\Context\ClientPortal\Application\Command\PublishInvoice\PublishInvoice;
use App\Context\ClientPortal\Application\Command\PublishPrescription\PublishPrescription;
use App\Context\ClientPortal\Application\Port\InvoiceDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Go-live import: publishes the invoices and prescriptions issued within
 * the last 24 months (never further back, never reports — only an explicit
 * share publishes those). Idempotent: an already registered source is
 * left as it is.
 */
#[AsCommand(name: 'app:client-portal:import-documents', description: 'Publish the invoices and prescriptions of the last 24 months on the Portal.')]
final readonly class ImportPortalDocumentsCommand
{
    private const string MAX_DEPTH = '-24 months';
    private const int    BATCH     = 200;

    public function __construct(
        private InvoiceDocumentsInterface $invoices,
        private PrescriptionDocumentsInterface $prescriptions,
        private CommandBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Oldest issue date (Y-m-d), capped at 24 months ago')] ?string $since = null): int
    {
        $floor = $this->clock->now()->modify(self::MAX_DEPTH);
        $given = null === $since ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $since, new \DateTimeZone('UTC'));

        if (false === $given || (null !== $given && $given->format('Y-m-d') !== $since)) {
            $io->error('--since expects a date as Y-m-d.');

            return Command::INVALID;
        }

        $from = null === $given ? $floor : max($floor, $given);

        $invoices = 0;
        $afterId  = null;

        do {
            $batch = $this->invoices->publishableSince($from, $afterId, self::BATCH);

            foreach ($batch as $source) {
                $this->commandBus->dispatch(new PublishInvoice($source->clinicId, $source->id));
                $afterId = $source->id;
                ++$invoices;
            }
        } while (self::BATCH === \count($batch));

        $prescriptions = 0;
        $afterId       = null;

        do {
            $batch = $this->prescriptions->publishableSince($from, $afterId, self::BATCH);

            foreach ($batch as $source) {
                $this->commandBus->dispatch(new PublishPrescription($source->clinicId, $source->id));
                $afterId = $source->id;
                ++$prescriptions;
            }
        } while (self::BATCH === \count($batch));

        $io->success(\sprintf('%d invoice(s) and %d prescription(s) offered to the Portal since %s.', $invoices, $prescriptions, $from->format('Y-m-d')));

        return Command::SUCCESS;
    }
}
