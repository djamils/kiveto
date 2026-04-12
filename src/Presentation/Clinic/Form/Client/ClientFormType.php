<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Form\Client;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ClientFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire.'),
                    new Length(max: 255, maxMessage: 'Le prénom ne peut pas dépasser 255 caractères.'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser 255 caractères.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'required'    => false,
                'constraints' => [
                    new Email(message: "Cette adresse email n'est pas valide."),
                ],
            ])
            ->add('phone', TelType::class, [
                'required'    => false,
                'constraints' => [
                    new Regex(
                        pattern: '/^[+\s]?[\d\s]{6,20}$/',
                        message: 'Le numéro de téléphone est invalide (ex : +33 6 12 34 56 78 ou 0612345678).',
                    ),
                ],
            ])
            ->add('_returnTo', HiddenType::class, [
                'mapped'   => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => null,
            'csrf_token_id' => 'create_client',
            'constraints'   => [
                new Callback([self::class, 'validateAtLeastOneContactMethod']),
            ],
        ]);
    }

    public static function validateAtLeastOneContactMethod(
        mixed $data,
        ExecutionContextInterface $context,
        mixed $payload = null,
    ): void {
        if (!\is_array($data)) {
            return;
        }

        $rawEmail = $data['email'] ?? '';
        $rawPhone = $data['phone'] ?? '';
        $email    = trim(\is_string($rawEmail) ? $rawEmail : '');
        $phone    = trim(\is_string($rawPhone) ? $rawPhone : '');

        if ('' === $email && '' === $phone) {
            $context->buildViolation('Au moins un moyen de contact (téléphone ou email) est requis.')
                ->addViolation()
            ;
        }
    }
}
