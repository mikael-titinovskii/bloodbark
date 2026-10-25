<?php

namespace App\Form;

use App\Entity\ContactType;
use App\Entity\ContactWindow;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The public sign-up: no password field, because the account is created with a generated one.
 *
 * The two checkboxes are not mapped onto the entity — they are assertions made at this moment,
 * and RegistrationService turns them into the stored consent timestamps and policy version.
 *
 * @extends AbstractType<User>
 */
final class RegistrationType extends AbstractType
{
    public function __construct(
        #[Autowire('%app.legal.policy_version%')] private readonly string $policyVersion,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'register.email',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('contactName', TextType::class, [
                'label' => 'register.contact_name',
                'help' => 'register.contact_name.help',
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('contactType', EnumType::class, [
                'class' => ContactType::class,
                'label' => 'register.contact_type',
                'placeholder' => 'register.contact_type.placeholder',
            ])
            ->add('contactWindow', EnumType::class, [
                'class' => ContactWindow::class,
                'label' => 'register.contact_window',
                'placeholder' => 'register.contact_window.placeholder',
            ])
            ->add('consent', CheckboxType::class, [
                'label' => new TranslatableMessage('register.consent', ['%version%' => $this->policyVersion]),
                'mapped' => false,
                'constraints' => [new Assert\IsTrue(message: 'register.consent.required')],
            ])
            ->add('adult', CheckboxType::class, [
                'label' => 'register.adult',
                'mapped' => false,
                'constraints' => [new Assert\IsTrue(message: 'register.adult.required')],
            ])
            ->add('submit', SubmitType::class, ['label' => 'register.submit']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'messages',
        ]);
    }
}
