<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SetPortalConsent;

use App\Context\ClientPortal\Application\Port\ClientConsentWriterInterface;
use App\Context\ClientPortal\Application\Service\PortalConsentFiles;
use App\Context\ClientPortal\Domain\Consent\PortalConsentKind;
use App\Context\ClientPortal\Domain\Exception\InvalidConsentChoiceException;
use App\Context\ClientPortal\Domain\Exception\OwnerProfileNotFoundException;
use App\Context\ClientPortal\Domain\Exception\UnknownConsentKindException;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Every file is written through the port inside this command's single
 * transaction: the change lands on all of them or on none. Each file writes
 * its own history rows, signed with the profile's full name.
 */
#[AsMessageHandler]
final readonly class SetPortalConsentHandler
{
    public function __construct(
        private PortalConsentFiles $files,
        private ClientConsentWriterInterface $writer,
        private OwnerProfileRepositoryInterface $profiles,
    ) {
    }

    public function __invoke(SetPortalConsent $command): void
    {
        $kind = PortalConsentKind::tryFrom($command->kind) ?? throw new UnknownConsentKindException($command->kind);

        foreach ($command->channels as $channel) {
            if (!\in_array($channel, PortalConsentKind::CHANNELS, true)) {
                throw InvalidConsentChoiceException::unknownChannel($channel);
            }
        }

        $channels = PortalConsentKind::orderedChannels($command->channels);

        if ([] === $channels && !$kind->isOptional()) {
            throw InvalidConsentChoiceException::channelRequired();
        }

        $records = $this->files->activeOf($command->userId, $command->clinicId);
        $profile = $this->profiles->findByUserId($command->userId) ?? throw new OwnerProfileNotFoundException($command->userId);
        $author  = trim($profile->firstName() . ' ' . $profile->lastName());

        foreach ($records as $record) {
            $this->writer->setConsent($command->clinicId, $record->clientId, $kind->value, $channels, $command->userId, $author);
        }
    }
}
