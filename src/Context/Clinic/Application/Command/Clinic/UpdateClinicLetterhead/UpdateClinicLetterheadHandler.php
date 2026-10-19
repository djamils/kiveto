<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead;

use App\Context\Clinic\Application\Service\InvoiceNumberingUniqueness;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PostalAddress;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateClinicLetterheadHandler
{
    public function __construct(
        private readonly ClinicRepositoryInterface $clinicRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly InvoiceNumberingUniqueness $numbering,
    ) {
    }

    public function __invoke(UpdateClinicLetterhead $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clinic   = $this->clinicRepository->findById($clinicId);

        if (null === $clinic) {
            throw new \RuntimeException(\sprintf('Clinic with ID "%s" not found.', $command->clinicId));
        }

        $letterhead = $this->letterhead($command);

        $this->numbering->assertChangeKeepsSeriesDistinct($clinic, $letterhead, $clinic->billingSettings());

        $clinic->updateLetterhead($letterhead, $this->clock->now());

        $this->clinicRepository->save($clinic);

        $this->domainEventPublisher->publish($clinic);
    }

    /**
     * An address needs a street, a city and a country to be one; anything less is
     * no address at all rather than a partial one.
     */
    private function letterhead(UpdateClinicLetterhead $command): Letterhead
    {
        $street  = self::trimmed($command->streetLine1);
        $city    = self::trimmed($command->city);
        $country = self::trimmed($command->countryCode);

        $address = null !== $street && null !== $city && null !== $country
            ? PostalAddress::create(
                streetLine1: $street,
                city: $city,
                countryCode: $country,
                streetLine2: $command->streetLine2,
                postalCode: $command->postalCode,
            )
            : null;

        return Letterhead::create(
            legalName: $command->legalName,
            legalForm: $command->legalForm,
            shareCapital: $command->shareCapital,
            siret: $command->siret,
            vatNumber: $command->vatNumber,
            rcsCity: $command->rcsCity,
            rcsNumber: $command->rcsNumber,
            apeCode: $command->apeCode,
            ordinalNumber: $command->ordinalNumber,
            address: $address,
            phone: $command->phone,
            email: $command->email,
            website: $command->website,
            ordinalCouncil: $command->ordinalCouncil,
            liabilityInsurer: $command->liabilityInsurer,
            liabilityPolicyNumber: $command->liabilityPolicyNumber,
        );
    }

    private static function trimmed(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
