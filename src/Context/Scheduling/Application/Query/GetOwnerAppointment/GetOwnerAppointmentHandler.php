<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetOwnerAppointment;

use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\PortalAppointmentItem;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GetOwnerAppointmentHandler
{
    public function __construct(
        private Connection $connection,
        private OnlineBookingSettingsRepositoryInterface $settings,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetOwnerAppointment $query): ?PortalAppointmentItem
    {
        if (!Uuid::isValid($query->appointmentId)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT ' . PortalAppointmentItem::COLUMNS
            . ' FROM scheduling__appointments a ' . PortalAppointmentItem::MOTIF_JOIN
            . ' WHERE a.id = :id AND a.clinic_id = UUID_TO_BIN(:clinicId)',
            ['id' => Uuid::fromString($query->appointmentId)->toBinary(), 'clinicId' => $query->clinicId],
            ['id' => ParameterType::BINARY],
        );

        if (false === $row) {
            return null;
        }

        return PortalAppointmentItem::fromRow($row, $this->settings->forClinic(ClinicId::fromString($query->clinicId)), $this->clock->now());
    }
}
