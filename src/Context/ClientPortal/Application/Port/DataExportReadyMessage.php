<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** « Your export is ready »: a notice only, the archive is downloaded in the app. */
final readonly class DataExportReadyMessage
{
    /**
     * @param string $expiresAtLabel « 04/11/2026 à 10:00 », Europe/Paris
     */
    public function __construct(
        public string $exportId,
        public string $userId,
        public string $recipientEmail,
        public string $recipientName,
        /** « Sophie », or '' without profile. */
        public string $recipientFirstName,
        public string $expiresAtLabel,
    ) {
    }
}
