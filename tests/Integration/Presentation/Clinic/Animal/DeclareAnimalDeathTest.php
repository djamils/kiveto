<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\Animal;

use App\Context\Animal\Domain\ValueObject\OwnershipRole;
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\Bus\Messenger\CommandBus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * FU-04: « Déclarer le décès » cancels the animal's appointments still to
 * come. A pending request confirmed by someone else between the list and its
 * cancel loses the decline race (« déjà traité »): it is cancelled once more
 * on its current status, so that no appointment of a dead animal stays
 * planned.
 */
final class DeclareAnimalDeathTest extends WebTestCase
{
    use Factories;

    private const string CLINIC  = '01960000-0000-7000-8000-0000000a4c01';
    private const string CLIENT  = '01960000-0000-7000-8000-0000000a4d01';
    private const string PENDING = '01960000-0000-7000-8000-0000000a4a01';
    private const string PLANNED = '01960000-0000-7000-8000-0000000a4a02';
    private const string HOST    = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private string $animalId = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $bus = self::getContainer()->get('messenger.bus.command');
        \assert($bus instanceof MessageBusInterface);
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        // The first cancel of the request loses the race to a confirmation committed while it waited
        // for the practitioner-day lock: the row is planned now, the cancel refused « déjà traité »
        self::getContainer()->set(CommandBusInterface::class, new class(new CommandBus($bus), $connection, self::PENDING) implements CommandBusInterface {
            private bool $confirmed = false;

            public function __construct(private readonly CommandBusInterface $inner, private readonly Connection $connection, private readonly string $pendingId)
            {
            }

            public function dispatch(object $command, object ...$stamps): mixed
            {
                if (!$this->confirmed && $command instanceof CancelAppointment && $this->pendingId === $command->appointmentId) {
                    $this->confirmed = true;
                    $this->connection->executeStatement(
                        'UPDATE scheduling__appointments SET status = ?, pending_expires_at = NULL WHERE id = ?',
                        [AppointmentStatus::PLANNED->value, Uuid::fromString($this->pendingId)->toBinary()],
                    );

                    throw new AppointmentAlreadyHandledException();
                }

                return $this->inner->dispatch($command, ...$stamps);
            }
        });

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Tilleuls')->create(['timeZone' => 'Europe/Paris']);
        $user = ClinicUserFactory::new()->withEmail('death.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $vet  = $user->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($vet)->asVeterinary()->asEmployee()->create();

        ClientEntityFactory::new()->withId(self::CLIENT)->withClinicId(self::CLINIC)->withName('Lucie', 'Garnier')->active()->create();
        $animalId = Uuid::v7();
        AnimalEntityFactory::new()->withClinicId(self::CLINIC)->withName('Pixel')->dog()->active()->alive()->create(['id' => $animalId]);
        OwnershipEntityFactory::createOne([
            'animal'   => AnimalEntityFactory::find(['id' => $animalId]),
            'clientId' => Uuid::fromString(self::CLIENT),
            'role'     => OwnershipRole::PRIMARY,
        ]);
        $this->animalId = $animalId->toRfc4122();

        foreach ([self::PENDING => '+3 days 08:00', self::PLANNED => '+4 days 08:00'] as $id => $when) {
            $booking = AppointmentEntityFactory::new()->withId($id)->withClinicId(self::CLINIC)->withPractitionerUserId($vet)
                ->withOwnerAndAnimal(self::CLIENT, $this->animalId)->startingAt($now->modify($when), 30)
            ;
            (self::PENDING === $id ? $booking->pending($now->modify('+2 days')) : $booking)->create();
        }

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"death.vet@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();
    }

    public function testARequestConfirmedMeanwhileIsCancelledAllTheSame(): void
    {
        $crawler = $this->browser->request('GET', self::HOST . '/animals/' . $this->animalId);
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('#dcdForm input[name="_token"]')->attr('value');

        $this->browser->request('POST', self::HOST . '/animals/' . $this->animalId . '/death', [
            '_token'      => $token,
            'deceased_on' => new \DateTimeImmutable('today')->format('Y-m-d'),
        ]);
        self::assertResponseStatusCodeSame(303);

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);
        $statuses = [];
        foreach ([self::PENDING, self::PLANNED] as $id) {
            $statuses[$id] = $connection->fetchOne('SELECT status FROM scheduling__appointments WHERE id = ?', [Uuid::fromString($id)->toBinary()]);
        }

        self::assertSame([self::PENDING => AppointmentStatus::CANCELLED->value, self::PLANNED => AppointmentStatus::CANCELLED->value], $statuses);

        $this->browser->followRedirect();
        self::assertStringContainsString('Décès de Pixel enregistré · 2 rendez-vous annulés.', (string) $this->browser->getResponse()->getContent());
    }
}
