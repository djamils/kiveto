<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RecordDataExport;

use App\Context\ClientPortal\Application\Port\DataExportJobsInterface;
use App\Context\ClientPortal\Application\Port\DataExportQuotaInterface;
use App\Context\ClientPortal\Application\Port\DataExportReadyMessage;
use App\Context\ClientPortal\Application\Port\NotificationRequesterInterface;
use App\Context\ClientPortal\Application\Port\OwnerAccountsInterface;
use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Ready: the archive's deletion is queued for 48 hours later and a verified
 * e-mail receives a notice, without link. Failed: the quota is given back.
 * An export no longer pending is left as it is; an archive stored for an
 * export deleted meanwhile (account deletion) is deleted at once.
 */
#[AsMessageHandler]
final readonly class RecordDataExportHandler
{
    private const string TIME_ZONE = 'Europe/Paris';

    public function __construct(
        private DataExportRepositoryInterface $exports,
        private DataExportJobsInterface $jobs,
        private DataExportQuotaInterface $quota,
        private OwnerAccountsInterface $accounts,
        private OwnerProfileRepositoryInterface $profiles,
        private NotificationRequesterInterface $notifications,
        private FileStorageInterface $storage,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordDataExport $command): void
    {
        $export = $this->exports->findById($command->exportId);

        if (null === $export) {
            if ($command->built) {
                $this->storage->delete(DataExport::keyOf($command->exportId));
            }

            return;
        }

        if (!$command->built) {
            if ($export->markFailed($this->clock->now())) {
                $this->exports->save($export);
                $this->quota->refund($export->userId());
            }

            return;
        }

        if (!$export->markReady($this->clock->now())) {
            return;
        }

        $this->exports->save($export);
        $this->jobs->deleteFileLater($export->id()->toString());
        $this->notify($export);
    }

    private function notify(DataExport $export): void
    {
        $email     = $this->accounts->accountOf($export->userId())?->verifiedEmail();
        $expiresAt = $export->expiresAt();

        if (null === $email || null === $expiresAt) {
            return;
        }

        $profile = $this->profiles->findByUserId($export->userId());

        $this->notifications->requestDataExportReady(new DataExportReadyMessage(
            exportId: $export->id()->toString(),
            userId: $export->userId(),
            recipientEmail: $email,
            recipientName: null === $profile ? '' : trim($profile->firstName() . ' ' . $profile->lastName()),
            recipientFirstName: $profile?->firstName() ?? '',
            expiresAtLabel: $expiresAt->setTimezone(new \DateTimeZone(self::TIME_ZONE))->format('d/m/Y à H:i'),
        ));
    }
}
