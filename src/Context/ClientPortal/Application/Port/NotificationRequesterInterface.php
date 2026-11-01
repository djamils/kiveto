<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Notification context: to be called last in the transaction. */
interface NotificationRequesterInterface
{
    public function requestPortalInvitation(PortalInvitationMessage $message): void;

    /** The e-mail notice of a ready data export (no link: the archive is downloaded in the app). */
    public function requestDataExportReady(DataExportReadyMessage $message): void;

    /**
     * The clinic's answer to an owner's request, on the client's transactional channel.
     *
     * @return string the id of the message (the existing one on a repeated key)
     */
    public function requestPortalRequestResolved(PortalRequestResolvedMessage $message): string;
}
