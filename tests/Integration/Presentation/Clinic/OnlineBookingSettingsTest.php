<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic;

use App\Context\Scheduling\Application\Query\GetOnlineBookingSettings\GetOnlineBookingSettings;
use App\Context\Scheduling\Application\Query\GetOnlineBookingSettings\OnlineBookingSettingsView;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\BookingMotifView;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\ListBookingMotifs;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Paramètres › Réservation en ligne (AC 38, AC 41): the defaults, the general
 * settings and the motifs saved as they change, out-of-bounds values refused
 * with Scheduling's French message, the warning of a clinic without opening
 * hours, and the section kept for the clinic's administrators.
 */
final class OnlineBookingSettingsTest extends WebTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000b5c01';
    private const string HOST   = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;
    private string $token        = '';
    private string $vetId        = '';
    private string $assistantId  = '';
    private string $receptionist = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
    }

    public function testTheDefaultsAreShownWithTheEligiblePractitionersAndTheMissingHoursWarning(): void
    {
        $this->clinic(withHours: false);
        $this->signIn('claire.admin@example.com');

        $payload = $this->open();
        $section = $payload['reservation-en-ligne'];
        \assert(\is_array($section));

        self::assertSame([
            'enabled'                 => false,
            'gridStepMinutes'         => 15,
            'minLeadTimeMinutes'      => 120,
            'minLeadTimeManualHours'  => 24,
            'horizonDays'             => 60,
            'cancellationWindowHours' => 24,
            'newClientBookingEnabled' => false,
        ], $section['settings']);
        self::assertSame([], $section['motifs']);
        self::assertTrue($section['hoursMissing']);

        // Veterinarian and assistant, never the front desk nor the manager.
        $practitioners = $section['practitioners'];
        \assert(\is_array($practitioners));
        $ids = array_map(static fn (mixed $pair): mixed => \is_array($pair) ? $pair[0] : null, $practitioners);
        sort($ids);
        $expected = [$this->vetId, $this->assistantId];
        sort($expected);
        self::assertSame($expected, $ids);
    }

    public function testAClinicWithOpeningHoursHasNoWarning(): void
    {
        $this->clinic(withHours: true);
        $this->signIn('claire.admin@example.com');

        $section = $this->open()['reservation-en-ligne'];
        \assert(\is_array($section));

        self::assertFalse($section['hoursMissing']);
    }

    public function testTheSettingsAndAMotifPersistAndTheMotifIsArchived(): void
    {
        $this->clinic(withHours: true);
        $this->signIn('claire.admin@example.com');
        $this->open();

        $this->post('/settings/online-booking', [
            'enabled'                 => true,
            'gridStepMinutes'         => 10,
            'minLeadTimeMinutes'      => 60,
            'minLeadTimeManualHours'  => 12,
            'horizonDays'             => 30,
            'cancellationWindowHours' => 48,
            'newClientBookingEnabled' => true,
        ]);
        self::assertResponseIsSuccessful();

        $settings = $this->queryBus()->ask(new GetOnlineBookingSettings(self::CLINIC));
        \assert($settings instanceof OnlineBookingSettingsView);
        self::assertSame((array) new OnlineBookingSettingsView(true, 10, 60, 12, 30, 48, true), (array) $settings);

        $motif = [
            'label'                   => 'Consultation',
            'description'             => 'Examen général',
            'durationMin'             => 30,
            'onlineEnabled'           => true,
            'confirmationKnownClient' => 'manual',
            'allowedBlockTypes'       => ['consultation'],
            'practitionerIds'         => [$this->vetId, $this->assistantId],
            'preventiveCareKind'      => null,
            'newClientsAllowed'       => false,
            'sortOrder'               => 1,
        ];
        $saved = $this->post('/settings/online-booking/motifs', $motif);
        self::assertResponseIsSuccessful();
        $id = $saved['id'] ?? null;
        \assert(\is_string($id));

        $motifs = $this->motifs();
        self::assertCount(1, $motifs);
        self::assertSame([$id, 'Consultation', 30, 'manual', ['consultation']], [$motifs[0]->id, $motifs[0]->label, $motifs[0]->durationMin, $motifs[0]->confirmationKnownClient, $motifs[0]->allowedBlockTypes]);
        $practitioners = $motifs[0]->practitionerIds;
        sort($practitioners);
        $expected = [$this->vetId, $this->assistantId];
        sort($expected);
        self::assertSame($expected, $practitioners);

        // Edited in place: same id, new values.
        $this->post('/settings/online-booking/motifs', ['id' => $id, 'durationMin' => 45, 'preventiveCareKind' => 'vaccination', 'practitionerIds' => [$this->vetId]] + $motif);
        self::assertResponseIsSuccessful();
        $motifs = $this->motifs();
        self::assertSame([$id, 45, 'vaccination', [$this->vetId]], [$motifs[0]->id, $motifs[0]->durationMin, $motifs[0]->preventiveCareKind, $motifs[0]->practitionerIds]);

        // The page shows it.
        $section = $this->open()['reservation-en-ligne'];
        \assert(\is_array($section) && \is_array($section['motifs']) && \is_array($section['motifs'][0]));
        self::assertSame($id, $section['motifs'][0]['id']);

        $this->post('/settings/online-booking/motifs/' . $id . '/archive', null);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->motifs());

        $this->post('/settings/online-booking/motifs/01960000-0000-7000-8000-0000000b5cff/archive', null);
        self::assertResponseStatusCodeSame(422);
        $this->post('/settings/online-booking/motifs/not-an-id/archive', null);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAnOutOfBoundsSettingIsRefusedWithAFrenchMessage(): void
    {
        $this->clinic(withHours: true);
        $this->signIn('claire.admin@example.com');
        $this->open();

        $answer = $this->post('/settings/online-booking', [
            'enabled'                 => true,
            'gridStepMinutes'         => 15,
            'minLeadTimeMinutes'      => 120,
            'minLeadTimeManualHours'  => 2,
            'horizonDays'             => 60,
            'cancellationWindowHours' => 24,
            'newClientBookingEnabled' => false,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([
            'error' => 'Le délai minimum d\'une demande à confirmer doit être compris entre 3 et 168 heures.',
            'field' => 'minLeadTimeManualHours',
        ], $answer);

        $answer = $this->post('/settings/online-booking', ['gridStepMinutes' => 15]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('minLeadTimeMinutes', $answer['field'] ?? null);

        // Nothing saved: still the defaults.
        $settings = $this->queryBus()->ask(new GetOnlineBookingSettings(self::CLINIC));
        \assert($settings instanceof OnlineBookingSettingsView);
        self::assertSame(24, $settings->minLeadTimeManualHours);
    }

    public function testAMotifOutOfBoundsOrForAMemberWhoCannotTakeAppointmentsIsRefused(): void
    {
        $this->clinic(withHours: true);
        $this->signIn('claire.admin@example.com');
        $this->open();

        $motif = [
            'label'                   => 'Vaccin',
            'durationMin'             => 20,
            'onlineEnabled'           => true,
            'confirmationKnownClient' => 'auto',
            'allowedBlockTypes'       => ['consultation'],
            'practitionerIds'         => [$this->vetId],
            'newClientsAllowed'       => false,
        ];

        $answer = $this->post('/settings/online-booking/motifs', ['practitionerIds' => [$this->receptionist]] + $motif);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'Ce praticien ne peut pas prendre de rendez-vous dans cette clinique.', 'field' => 'practitionerIds'], $answer);

        $answer = $this->post('/settings/online-booking/motifs', ['practitionerIds' => ['not-an-id']] + $motif);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('practitionerIds', $answer['field'] ?? null);

        $answer = $this->post('/settings/online-booking/motifs', ['durationMin' => 7] + $motif);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'La durée doit être un multiple de 5 minutes.', 'field' => 'durationMin'], $answer);

        $answer = $this->post('/settings/online-booking/motifs', ['durationMin' => null] + $motif);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('durationMin', $answer['field'] ?? null);

        $answer = $this->post('/settings/online-booking/motifs', ['allowedBlockTypes' => ['leave']] + $motif);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('allowedBlockTypes', $answer['field'] ?? null);

        $this->post('/settings/online-booking/motifs', ['id' => 'not-an-id'] + $motif);
        self::assertResponseStatusCodeSame(422);
        $this->post('/settings/online-booking/motifs', ['id' => '01960000-0000-7000-8000-0000000b5cff'] + $motif);
        self::assertResponseStatusCodeSame(422);

        self::assertSame([], $this->motifs());
    }

    public function testAMemberWithoutTheStaffRightCannotOpenNorSaveTheSection(): void
    {
        $this->clinic(withHours: true);
        $this->signIn('hugo.vet@example.com');

        $this->browser->request('GET', self::HOST . '/settings/online-booking');
        self::assertResponseStatusCodeSame(403);

        $this->browser->request('GET', self::HOST . '/settings/preferences');
        $this->token = $this->tokenOf((string) $this->browser->getResponse()->getContent());

        $this->post('/settings/online-booking', ['enabled' => true]);
        self::assertResponseStatusCodeSame(403);
        $this->post('/settings/online-booking/motifs', ['label' => 'Consultation']);
        self::assertResponseStatusCodeSame(403);
        $this->post('/settings/online-booking/motifs/01960000-0000-7000-8000-0000000b5cff/archive', null);
        self::assertResponseStatusCodeSame(403);
    }

    private function clinic(bool $withHours): void
    {
        $factory = ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique du Parc');

        if ($withHours) {
            $factory = $factory->withOpeningHours(['week' => [1 => [['opensAt' => '09:00', 'closesAt' => '19:00']]], 'closures' => []]);
        }

        $factory->create(['timeZone' => 'Europe/Paris']);

        $admin = ClinicUserFactory::new()->withEmail('claire.admin@example.com')->withPlainPassword('mot-de-passe')->create();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($admin->getId()->toRfc4122())->asManager()->asEmployee()->asClinicAdministrator()->create();

        $vet         = ClinicUserFactory::new()->withEmail('hugo.vet@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->vetId = $vet->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($this->vetId)->asVeterinary()->asEmployee()->create();

        $assistant         = ClinicUserFactory::new()->withEmail('lea.asv@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->assistantId = $assistant->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($this->assistantId)->asVeterinaryAssistant()->asEmployee()->create();

        $desk               = ClinicUserFactory::new()->withEmail('nina.accueil@example.com')->withPlainPassword('mot-de-passe')->create();
        $this->receptionist = $desk->getId()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($this->receptionist)->asReceptionist()->asEmployee()->create();
    }

    private function signIn(string $email): void
    {
        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode(['email' => $email, 'password' => 'mot-de-passe'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }

    /**
     * Opens the section and keeps the page's token.
     *
     * @return array<array-key, mixed>
     */
    private function open(): array
    {
        $crawler = $this->browser->request('GET', self::HOST . '/settings/online-booking');
        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $crawler->filter('[data-payload]')->attr('data-payload'), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($payload) && \is_string($payload['token']));
        self::assertSame('reservation-en-ligne', $payload['section']);
        $this->token = $payload['token'];

        return $payload;
    }

    private function tokenOf(string $page): string
    {
        self::assertSame(1, preg_match('/&quot;token&quot;:&quot;([^&]+)&quot;/', $page, $match));

        return $match[1];
    }

    /**
     * @param array<string, mixed>|null $values
     *
     * @return array<array-key, mixed>
     */
    private function post(string $path, ?array $values): array
    {
        $fields = ['_token' => $this->token] + (null !== $values ? ['values' => json_encode($values, \JSON_THROW_ON_ERROR)] : []);
        $this->browser->request('POST', self::HOST . $path, $fields, server: ['HTTP_ACCEPT' => 'application/json']);

        $answer = json_decode((string) $this->browser->getResponse()->getContent(), true);

        return \is_array($answer) ? $answer : [];
    }

    /**
     * @return list<BookingMotifView>
     */
    private function motifs(): array
    {
        $motifs = $this->queryBus()->ask(new ListBookingMotifs(self::CLINIC));
        \assert(\is_array($motifs));

        return array_values(array_filter($motifs, static fn (mixed $motif): bool => $motif instanceof BookingMotifView));
    }

    private function queryBus(): QueryBusInterface
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        return $bus;
    }
}
