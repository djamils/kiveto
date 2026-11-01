<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use App\Context\ClientPortal\Application\Query\GetOwnerAppointmentDetail\GetOwnerAppointmentDetail;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\PortalAppointment;
use App\Context\ClientPortal\Domain\Exception\AppointmentNotFoundException;
use App\Context\ClientPortal\Domain\Exception\ClinicNotLinkedException;
use App\Context\ClientPortal\Domain\Exception\InvalidNewPetException;
use App\Context\ClientPortal\Domain\Exception\PetNotBookableException;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Exception\AppointmentNotModifiableException;
use App\Context\Scheduling\Domain\Exception\CancellationWindowClosedException;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\View\AppointmentView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\CommandInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Runs a ClientPortal booking command (book, reschedule, cancel), maps its
 * refusals to the Portal's problems and answers the appointment it names,
 * re-read as the owner sees it.
 */
final readonly class AppointmentResponder
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
    ) {
    }

    public function run(CommandInterface $command, string $userId, string $clinicId): AppointmentView
    {
        try {
            $appointmentId = $this->commandBus->dispatch($command);
        } catch (PetNotFoundException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND, previous: $exception);
        } catch (AppointmentNotFoundException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND, previous: $exception);
        } catch (ClinicNotLinkedException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND, previous: $exception);
        } catch (OnlineBookingNotFoundException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND, previous: $exception);
        } catch (PetNotBookableException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::PET_NOT_BOOKABLE, previous: $exception);
        } catch (BookingNotOpenException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::BOOKING_NOT_OPEN, previous: $exception);
        } catch (SlotTakenException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::SLOT_TAKEN, previous: $exception);
        } catch (CancellationWindowClosedException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::CANCELLATION_CLOSED, previous: $exception);
        } catch (AppointmentNotModifiableException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_MODIFIABLE, previous: $exception);
        } catch (InvalidNewPetException $exception) {
            throw PortalProblemMap::violation('newPet.' . $exception->field, $exception->getMessage());
        }

        \assert(\is_string($appointmentId));

        $appointment = $this->queryBus->ask(new GetOwnerAppointmentDetail($userId, $clinicId, $appointmentId));

        if (!$appointment instanceof PortalAppointment) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }

        return AppointmentView::of($appointment);
    }

    /** An ISO 8601 instant with its offset (checked by the input's constraint). */
    public static function instant(string $value): \DateTimeImmutable
    {
        $instant = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $value);
        \assert($instant instanceof \DateTimeImmutable);

        return $instant;
    }
}
