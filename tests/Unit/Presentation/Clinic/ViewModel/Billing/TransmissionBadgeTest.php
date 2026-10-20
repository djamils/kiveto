<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Billing;

use App\Context\Billing\Application\Query\GetInvoiceDetails\TransmissionView;
use App\Presentation\Clinic\ViewModel\Billing\TransmissionBadge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransmissionBadgeTest extends TestCase
{
    private const string SUPPORT = 'Contactez le support Kiveto.';
    private const string REISSUE = 'Créez un avoir puis une nouvelle facture.';

    public function testAPrivateDocumentHasNoBadge(): void
    {
        self::assertNull(TransmissionBadge::of(null));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideStatesWithoutActionShowTheirPillOnlyCases(): iterable
    {
        yield 'requested' => ['requested', 'Transmission demandée', 'wait'];
        yield 'pending connection' => ['pending_connection', 'Plateforme non connectée', 'wait'];
        yield 'queued' => ['queued', 'En attente d\'envoi', 'wait'];
        yield 'submitting' => ['submitting', 'En attente d\'envoi', 'wait'];
        yield 'submitted' => ['submitted', 'Transmise', 'sync'];
        yield 'not required' => ['not_required', 'Non transmis — facture d\'origine annulée', 'info'];
        yield 'accepted without a status yet' => ['accepted', 'Transmise', 'sync'];
    }

    #[DataProvider('provideStatesWithoutActionShowTheirPillOnlyCases')]
    public function testStatesWithoutActionShowTheirPillOnly(string $state, string $text, string $tone): void
    {
        self::assertSame(['text' => $text, 'tone' => $tone], TransmissionBadge::of(self::view($state)));
    }

    public function testAFailedRequestAsksToContactTheSupport(): void
    {
        self::assertSame([
            'text'   => 'Transmission non demandée',
            'tone'   => 'alert',
            'action' => ['title' => 'Action requise', 'next' => self::SUPPORT, 'reason' => null],
        ], TransmissionBadge::of(self::view('request_failed')));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function provideABlockedTransmissionNamesItsReasonAndAsksToContactTheSupportCases(): iterable
    {
        yield 'rendering' => ['RENDERING_FAILED', 'Erreur de génération'];
        yield 'organisation' => ['ORGANISATION_MISMATCH', 'SIREN de la connexion incorrect'];
        yield 'refused' => ['PLATFORM_REFUSED', 'Refusé par la plateforme'];
        yield 'exhausted' => ['SUBMISSION_ATTEMPTS_EXHAUSTED', 'Envoi impossible'];
        yield 'duplicate' => ['DUPLICATE_FLOW', 'Double dépôt détecté'];
        yield 'unknown' => [null, 'Envoi bloqué'];
    }

    #[DataProvider('provideABlockedTransmissionNamesItsReasonAndAsksToContactTheSupportCases')]
    public function testABlockedTransmissionNamesItsReasonAndAsksToContactTheSupport(?string $reason, string $text): void
    {
        self::assertSame([
            'text'   => $text,
            'tone'   => 'alert',
            'action' => ['title' => 'Action requise', 'next' => self::SUPPORT, 'reason' => 'Le PDF n\'a pas pu être produit'],
        ], TransmissionBadge::of(self::view('blocked', blockReason: $reason, reason: 'Le PDF n\'a pas pu être produit', requiresAction: true)));
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function provideALifecycleStatusShowsItsFrenchLabelCases(): iterable
    {
        yield '203 on its way' => [203, 'Mise à disposition', 'wait'];
        yield '205 approved' => [205, 'Approuvée', 'ok'];
        yield '206 partially approved' => [206, 'Approuvée partiellement', 'ok'];
        yield '212 paid' => [212, 'Encaissée', 'ok'];
    }

    #[DataProvider('provideALifecycleStatusShowsItsFrenchLabelCases')]
    public function testALifecycleStatusShowsItsFrenchLabel(int $code, string $label, string $tone): void
    {
        self::assertSame(['text' => $label, 'tone' => $tone], TransmissionBadge::of(self::view('accepted', code: $code, label: $label)));
    }

    public function testAStatusWithoutLabelShowsItsCode(): void
    {
        self::assertSame(['text' => 'Statut 204', 'tone' => 'wait'], TransmissionBadge::of(self::view('accepted', code: 204)));
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function provideAStatusToActOnRaisesTheBannerWithItsNextStepCases(): iterable
    {
        yield '207 disputed' => [207, 'En litige', 'Contactez le client.'];
        yield '208 suspended' => [208, 'Suspendue', 'Contactez le client.'];
        yield '210 refused' => [210, 'Refusée', self::REISSUE];
        yield '213 rejected' => [213, 'Rejetée', self::REISSUE];
        yield '220 cancelled' => [220, 'Annulée', self::REISSUE];
        yield '221 routing error' => [221, 'Erreur de routage', self::SUPPORT];
    }

    #[DataProvider('provideAStatusToActOnRaisesTheBannerWithItsNextStepCases')]
    public function testAStatusToActOnRaisesTheBannerWithItsNextStep(int $code, string $label, string $next): void
    {
        self::assertSame([
            'text'   => $label,
            'tone'   => 'alert',
            'action' => ['title' => 'Action requise', 'next' => $next, 'reason' => 'Montant contesté'],
        ], TransmissionBadge::of(self::view('accepted', code: $code, label: $label, reasonCode: 'AUTRE', reason: 'Montant contesté', requiresAction: 220 !== $code)));
    }

    public function testAnInadmissibleDocumentAsksToContactTheSupport(): void
    {
        self::assertSame([
            'text'   => 'Irrecevable',
            'tone'   => 'alert',
            'action' => ['title' => 'Action requise', 'next' => self::SUPPORT, 'reason' => null],
        ], TransmissionBadge::of(self::view('inadmissible', code: 501, label: 'Irrecevable (plateforme)', reason: '  ', requiresAction: true)));
    }

    public function testADepositForABuyerWithoutPlatformNeedsNoAction(): void
    {
        self::assertSame(
            ['text' => 'Déposée — client sans plateforme', 'tone' => 'info'],
            TransmissionBadge::of(self::view('accepted', code: 200, label: 'Déposée', reasonCode: 'NON_TRANSMISE', requiresAction: true)),
        );
    }

    public function testAPlainDepositIsOnItsWay(): void
    {
        self::assertSame(['text' => 'Déposée', 'tone' => 'wait'], TransmissionBadge::of(self::view('submitted', code: 200, label: 'Déposée')));
    }

    public function testAnUnlistedStatusToActOnFallsBackOnTheSupport(): void
    {
        self::assertSame([
            'text'   => 'Statut inconnu',
            'tone'   => 'alert',
            'action' => ['title' => 'Action requise', 'next' => self::SUPPORT, 'reason' => null],
        ], TransmissionBadge::of(self::view('accepted', code: 299, label: 'Statut inconnu', requiresAction: true)));
    }

    private static function view(
        string $state,
        ?string $blockReason = null,
        ?int $code = null,
        ?string $label = null,
        ?string $reasonCode = null,
        ?string $reason = null,
        bool $requiresAction = false,
    ): TransmissionView {
        return new TransmissionView($state, $blockReason, $code, $label, $reasonCode, $reason, $requiresAction);
    }
}
