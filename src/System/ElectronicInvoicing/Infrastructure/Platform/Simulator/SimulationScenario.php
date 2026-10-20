<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;

/**
 * What the simulated platform answers, chosen by the buyer SIREN (demo SIRENs,
 * Luhn-valid). Any other buyer follows the nominal path.
 */
enum SimulationScenario: string
{
    case NOMINAL         = 'nominal';
    case REJECTED        = 'rejected';
    case REFUSED         = 'refused';
    case DISPUTED        = 'disputed';
    case INADMISSIBLE    = 'inadmissible';
    case NOT_TRANSMITTED = 'not_transmitted';

    public const string BUYER_REJECTED        = '000002139';
    public const string BUYER_REFUSED         = '000002105';
    public const string BUYER_DISPUTED        = '000002071';
    public const string BUYER_INADMISSIBLE    = '000005017';
    public const string BUYER_NOT_TRANSMITTED = '000000000';

    public static function forBuyerSiren(?string $siren): self
    {
        return match ($siren) {
            self::BUYER_REJECTED        => self::REJECTED,
            self::BUYER_REFUSED         => self::REFUSED,
            self::BUYER_DISPUTED        => self::DISPUTED,
            self::BUYER_INADMISSIBLE    => self::INADMISSIBLE,
            self::BUYER_NOT_TRANSMITTED => self::NOT_TRANSMITTED,
            default                     => self::NOMINAL,
        };
    }

    /**
     * The statuses the platform emits, in order, as [code, reason code].
     *
     * @return non-empty-list<array{LifecycleStatusCode, ?string}>
     */
    public function steps(): array
    {
        $received = [
            [LifecycleStatusCode::DEPOSITED, null],
            [LifecycleStatusCode::RECEIVED, null],
            [LifecycleStatusCode::MADE_AVAILABLE, null],
        ];

        return match ($this) {
            self::NOMINAL         => [...$received, [LifecycleStatusCode::APPROVED, null]],
            self::REJECTED        => [[LifecycleStatusCode::DEPOSITED, null], [LifecycleStatusCode::REJECTED, 'REJ_SEMAN']],
            self::REFUSED         => [...$received, [LifecycleStatusCode::REFUSED, 'DOUBLON']],
            self::DISPUTED        => [...$received, [LifecycleStatusCode::DISPUTED, 'TX_TVA_ERR']],
            self::INADMISSIBLE    => [[LifecycleStatusCode::INADMISSIBLE, 'IRR_SYNTAX']],
            self::NOT_TRANSMITTED => [[LifecycleStatusCode::DEPOSITED, 'NON_TRANSMISE']],
        };
    }
}
