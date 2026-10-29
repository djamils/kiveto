<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Service;

use App\Context\Clinic\Application\Port\ClinicMessagingProfileData;
use App\Context\Clinic\Application\Port\MessagingProfileSyncInterface;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;

/**
 * Hands Notification the current messaging profile of a clinic: its name,
 * slug, reply-to, SMS settings, coordinates and branding, as they stand.
 */
final readonly class MessagingProfilePublisher
{
    public function __construct(
        private ClinicRepositoryInterface $clinics,
        private MessagingProfileSyncInterface $sync,
    ) {
    }

    public function publish(string $clinicId): void
    {
        $clinic = $this->clinics->findById(ClinicId::fromString($clinicId));

        if (null === $clinic) {
            return;
        }

        $letterhead = $clinic->letterhead();
        $address    = $letterhead->address;
        $messaging  = $clinic->messagingSettings();
        $locality   = null !== $address ? mb_trim(($address->postalCode ?? '') . ' ' . $address->city) : '';

        $this->sync->pushProfile(new ClinicMessagingProfileData(
            clinicId: $clinic->id()->toString(),
            name: $clinic->name(),
            emailLocalPart: $clinic->slug()->toString(),
            replyTo: $letterhead->email?->toString(),
            smsSender: $messaging->smsSender,
            overageCapSegments: $messaging->overageCapSegments,
            addressLine: null !== $address ? $address->streetLine1 . ', ' . $locality : '',
            city: $address->city ?? '',
            phone: $letterhead->phone?->toString(),
            brandColor: $clinic->brandColor()?->toString(),
            logoStorageKey: $clinic->logoKey(),
        ));
    }
}
