<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The new password a reset link lets the account holder choose. Unlike registration — where the
 * password is generated and mailed — this one is typed twice by a human, so it carries the length
 * and strength constraints.
 *
 * @extends AbstractType<mixed>
 */
final class ResetPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'reset.password.mismatch',
                'first_options' => [
                    'label' => 'reset.password',
                    'help' => 'reset.password.help',
                    'attr' => ['autocomplete' => 'new-password'],
                    // The constraints sit on the first field on purpose: the second one only has to
                    // match it, and a violation shown twice reads like two problems.
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Length(min: 12, minMessage: 'reset.password.too_short', max: 4096),
                        new Assert\PasswordStrength(),
                    ],
                ],
                'second_options' => [
                    'label' => 'reset.password_repeat',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => 'reset.submit_new']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'messages']);
    }
}
