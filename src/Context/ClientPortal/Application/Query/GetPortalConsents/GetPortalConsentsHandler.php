<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalConsents;

use App\Context\ClientPortal\Application\Service\PortalConsentFiles;
use App\Context\ClientPortal\Domain\Consent\PortalConsentKind;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Per kind, the union of the channels granted on the files. Its date is the
 * latest `grantedAt` among the files holding the kind: the date from which
 * every channel shown is consented (a channel added later re-dates the
 * consent of its file). The preferred channel is the one of the oldest
 * linked file that has one.
 */
#[AsMessageHandler]
final readonly class GetPortalConsentsHandler
{
    public function __construct(private PortalConsentFiles $files)
    {
    }

    public function __invoke(GetPortalConsents $query): PortalConsents
    {
        $records   = $this->files->activeOf($query->userId, $query->clinicId);
        $preferred = null;

        foreach ($records as $record) {
            $preferred ??= $record->preferredChannel;
        }

        $consents = [];

        foreach (PortalConsentKind::cases() as $kind) {
            $channels  = [];
            $grantedAt = null;

            foreach ($records as $record) {
                $grant = $record->consents[$kind->value] ?? null;

                if (null === $grant || [] === $grant->channels) {
                    continue;
                }

                $channels  = [...$channels, ...$grant->channels];
                $grantedAt = null === $grantedAt || $grant->grantedAt > $grantedAt ? $grant->grantedAt : $grantedAt;
            }

            $channels   = PortalConsentKind::orderedChannels($channels);
            $consents[] = new PortalConsent($kind->value, $channels, [] === $channels ? null : $grantedAt, $kind->effect());
        }

        return new PortalConsents($preferred, $consents);
    }
}
