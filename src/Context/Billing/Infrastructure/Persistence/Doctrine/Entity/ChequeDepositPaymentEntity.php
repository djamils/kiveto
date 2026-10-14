<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A cheque of a remittance. Keyed on the payment: a cheque goes to the bank once.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\Index(name: 'idx_cheque_deposit_payment_deposit', columns: ['deposit_id'])]
class ChequeDepositPaymentEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'payment_id', type: UuidType::NAME)]
    private Uuid $paymentId;

    #[ORM\Column(name: 'deposit_id', type: UuidType::NAME)]
    private Uuid $depositId;

    public function getPaymentId(): Uuid
    {
        return $this->paymentId;
    }

    public function setPaymentId(Uuid $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function getDepositId(): Uuid
    {
        return $this->depositId;
    }

    public function setDepositId(Uuid $depositId): void
    {
        $this->depositId = $depositId;
    }
}
