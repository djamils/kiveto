<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\UpsertClinicMessagingProfile;

use App\System\Notification\Domain\Clinic\ClinicMessagingProfile;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfileRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpsertClinicMessagingProfileHandler
{
    public function __construct(private ClinicMessagingProfileRepositoryInterface $profiles)
    {
    }

    public function __invoke(UpsertClinicMessagingProfile $command): void
    {
        $this->profiles->save(ClinicMessagingProfile::of(
            clinicId: $command->clinicId,
            name: $command->name,
            emailLocalPart: $command->emailLocalPart,
            replyTo: $command->replyTo,
            smsSender: $command->smsSender,
            overageCapSegments: $command->overageCapSegments,
            addressLine: $command->addressLine,
            city: $command->city,
            phoneDisplay: $command->phoneDisplay,
            brandColor: $command->brandColor,
            logoStorageKey: $command->logoStorageKey,
        ));
    }
}
