<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedName;
use App\Context\Scheduling\Application\Port\ClinicCard;
use App\Context\Scheduling\Application\Port\ClinicDirectoryInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Clinic's public queries: GetClinic (time zone, name, letterhead e-mail and
 * address) and GetStaffPrintedName. Without a letterhead e-mail, the
 * organizer of the calendar invitations is the clinic's sending address —
 * its slug at the notification domain, as Clinic's messaging profile has it.
 */
final readonly class QueryBusClinicDirectory implements ClinicDirectoryInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        #[Autowire(env: 'NOTIFICATION_EMAIL_DOMAIN')]
        private string $emailDomain,
    ) {
    }

    public function clinic(string $clinicId): ClinicCard
    {
        $clinic = $this->queryBus->ask(new GetClinic($clinicId));

        if (!$clinic instanceof ClinicDto) {
            throw new \DomainException(\sprintf('Clinic "%s" not found.', $clinicId));
        }

        $address = implode(', ', $clinic->letterhead->addressLines ?? []);

        return new ClinicCard(
            name: $clinic->name,
            timeZone: new \DateTimeZone($clinic->timeZone),
            email: $clinic->letterhead->email ?? $clinic->slug . '@' . $this->emailDomain,
            address: '' !== $address ? $address : null,
        );
    }

    public function printedName(string $clinicId, string $userId): ?string
    {
        $name = $this->queryBus->ask(new GetStaffPrintedName($clinicId, $userId));

        return \is_string($name) ? $name : null;
    }
}
