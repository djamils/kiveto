<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Pipeline;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId as ClientClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\PostNewAppointmentNotice\PostNewAppointmentNotice;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\System\Notification\Application\Command\MarkInAppNotificationRead\MarkInAppNotificationRead;
use App\System\Notification\Application\Query\ListInAppNotifications\InAppNotificationView;
use App\System\Notification\Application\Query\ListInAppNotifications\ListInAppNotifications;
use App\Tests\Support\MessengerDrain;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 38 / AC 40 through the real wiring: the « Nouveau RDV » job consumed
 * by the `async` worker posts to Dr Rousseau's bell an entry that opens the
 * agenda on the appointment; the cancellation withdraws it while unread.
 */
final class NewAppointmentBellPipelineTest extends KernelTestCase
{
    use Factories;

    private const string JULIE = '01960000-0000-7000-8000-0000000d0009';

    protected function setUp(): void
    {
        ClinicEntityFactory::new()->withId(Appointments::CLINIC)->create(['timeZone' => 'Europe/Paris', 'name' => 'Clinique Montmartre']);

        $clients = self::getContainer()->get(ClientRepositoryInterface::class);
        \assert($clients instanceof ClientRepositoryInterface);
        $clients->save(Client::create(
            ClientId::fromString(Appointments::OWNER),
            ClientClinicId::fromString(Appointments::CLINIC),
            new ClientIdentity('Sophie', 'Dubois', Civility::MADAM),
            [ContactMethod::email(EmailAddress::fromString('s.dubois@email.fr'), ContactLabel::HOME, true)],
            new \DateTimeImmutable('2026-01-12 10:00:00'),
        ));

        $appointments = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($appointments instanceof AppointmentRepositoryInterface);
        $appointments->save(Appointments::planned('2030-03-05 08:30', animalId: null));
    }

    public function testTheVeterinarianSeesTheBookingUntilItIsCancelled(): void
    {
        $this->bookedBy(self::JULIE);

        $entries = $this->bell();
        self::assertCount(1, $entries);
        self::assertSame(['NEW_APPOINTMENT', 'Nouveau RDV', 'Sophie Dubois · mardi 05/03 à 9h30', false], [$entries[0]->kind, $entries[0]->title, $entries[0]->detail, $entries[0]->read]);
        self::assertStringEndsWith('/scheduling/agenda?rdv=' . Appointments::ID . '&date=2030-03-05&view=day', $this->url($entries[0]));

        $this->commandBus()->dispatch(new CancelAppointment(Appointments::ID, Appointments::CLINIC));
        MessengerDrain::drain(self::getContainer(), ['async']);

        self::assertSame([], $this->bell());
    }

    public function testAReadEntryStaysAfterTheCancellation(): void
    {
        $this->bookedBy(self::JULIE);
        $this->commandBus()->dispatch(new MarkInAppNotificationRead($this->bell()[0]->id, Appointments::ROUSSEAU));

        $this->commandBus()->dispatch(new CancelAppointment(Appointments::ID, Appointments::CLINIC));
        MessengerDrain::drain(self::getContainer(), ['async']);

        self::assertCount(1, $this->bell());
    }

    public function testNothingIsPostedWhenTheVeterinarianBookedThemself(): void
    {
        $this->bookedBy(Appointments::ROUSSEAU);

        self::assertSame([], $this->bell());
    }

    private function bookedBy(string $actor): void
    {
        $bus = self::getContainer()->get('messenger.bus.command');
        \assert($bus instanceof MessageBusInterface);
        $bus->dispatch(new PostNewAppointmentNotice(Appointments::ID, $actor));

        MessengerDrain::drain(self::getContainer(), ['async']);
    }

    /**
     * @return list<InAppNotificationView>
     */
    private function bell(): array
    {
        $queryBus = self::getContainer()->get(QueryBusInterface::class);
        \assert($queryBus instanceof QueryBusInterface);
        $entries = $queryBus->ask(new ListInAppNotifications(Appointments::ROUSSEAU, Appointments::CLINIC));
        \assert(\is_array($entries));

        return array_values(array_filter($entries, static fn (mixed $e): bool => $e instanceof InAppNotificationView));
    }

    private function url(InAppNotificationView $entry): string
    {
        $router = self::getContainer()->get(UrlGeneratorInterface::class);
        \assert($router instanceof UrlGeneratorInterface);

        return $router->generate($entry->route, $entry->routeParams);
    }

    private function commandBus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }
}
