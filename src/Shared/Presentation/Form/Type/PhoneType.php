<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Form\Type;

use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class PhoneType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound'    => false,
            'empty_data'  => '',
            'constraints' => [
                new Callback([self::class, 'validatePhone']),
            ],
        ]);
    }

    public static function validatePhone(
        mixed $value,
        ExecutionContextInterface $context,
        mixed $payload = null,
    ): void {
        if (!\is_string($value) || '' === $value) {
            return;
        }

        try {
            PhoneNumber::fromString($value);
        } catch (\InvalidArgumentException) {
            $context->buildViolation('Le numéro de téléphone est invalide.')
                ->addViolation()
            ;
        }
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
