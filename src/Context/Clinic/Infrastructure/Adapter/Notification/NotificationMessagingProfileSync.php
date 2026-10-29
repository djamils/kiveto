<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\Notification;

use App\Context\Clinic\Application\Port\ClinicMessagingProfileData;
use App\Context\Clinic\Application\Port\MessagingProfileSyncInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Presentation\Twig\PhoneFormatRuntime;
use App\System\Notification\Application\Command\RecordVeterinarianCount\RecordVeterinarianCount;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;

/**
 * Hands Notification its read-only copy of what it needs about a clinic,
 * through Notification's public commands.
 */
final readonly class NotificationMessagingProfileSync implements MessagingProfileSyncInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private PhoneFormatRuntime $phones,
    ) {
    }

    public function pushProfile(ClinicMessagingProfileData $profile): void
    {
        $this->commandBus->dispatch(new UpsertClinicMessagingProfile(
            clinicId: $profile->clinicId,
            name: $profile->name,
            emailLocalPart: $profile->emailLocalPart,
            replyTo: $profile->replyTo,
            smsSender: $profile->smsSender,
            overageCapSegments: $profile->overageCapSegments,
            addressLine: $profile->addressLine,
            city: $profile->city,
            // « 01 42 64 18 00 », as the clinic's own pages print it.
            phoneDisplay: null !== $profile->phone ? $this->phones->phoneDisplay($profile->phone, false, false) : '',
            brandColor: $profile->brandColor,
            logoStorageKey: $profile->logoStorageKey,
        ));
    }

    public function pushVeterinarianCount(string $clinicId, string $day, int $count): void
    {
        $this->commandBus->dispatch(new RecordVeterinarianCount($clinicId, $day, $count));
    }
}
