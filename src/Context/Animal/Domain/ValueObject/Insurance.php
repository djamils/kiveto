<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

/**
 * The animal's health insurance: the insurer and the contract number the
 * clinic quotes on a claim form.
 */
final readonly class Insurance
{
    public string $insurerName;

    public string $policyNumber;

    public function __construct(string $insurerName, string $policyNumber)
    {
        $insurerName  = trim($insurerName);
        $policyNumber = mb_strtoupper(trim($policyNumber));

        if ('' === $insurerName || mb_strlen($insurerName) > 80) {
            throw new \InvalidArgumentException('Insurer name must be 1 to 80 characters long');
        }

        if (1 !== preg_match('/^[A-Z0-9-]{4,20}$/', $policyNumber)) {
            throw new \InvalidArgumentException('Insurance policy number must be 4 to 20 letters, digits or dashes');
        }

        $this->insurerName  = $insurerName;
        $this->policyNumber = $policyNumber;
    }
}
