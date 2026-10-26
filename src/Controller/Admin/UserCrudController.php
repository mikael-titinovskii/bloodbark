<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

/**
 * Accounts, as a super admin sees them: read the consent and confirmation record, fix a contact
 * preference, delete the account. Nothing here creates an account or changes a password — the
 * registration flow owns both and mails the generated password once.
 *
 * The address is the login identifier and the confirmation record hangs off it, so the email is
 * read-only in the form. Promotion stays on `app:user:promote` for now: the entity exposes
 * `addRole()` and no setter, deliberately, so a form cannot quietly replace the whole role list.
 *
 * @extends AbstractCrudController<User>
 */
final class UserCrudController extends AbstractCrudController
{
    /**
     * Message key per role constant. ROLE_USER is absent because every account has it: it is what
     * "no extra role" looks like, and printing it in every row would say nothing.
     */
    private const array ROLE_KEYS = [
        User::ROLE_MODERATOR => 'admin.role.moderator',
        User::ROLE_SUPER_ADMIN => 'admin.role.super_admin',
    ];

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('admin.user.singular'))
            ->setEntityLabelInPlural(t('admin.user.plural'))
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['email', 'contactName']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // "New" would mean typing a password and a consent record for somebody else. Creation
        // belongs to /register, so the button goes rather than being left there to confuse.
        return $actions->disable(Action::NEW);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email', t('account.email')))
            ->add(TextFilter::new('contactName', t('account.contact_name')))
            ->add(DateTimeFilter::new('createdAt', t('admin.user.created_at')));
    }

    /**
     * Disabling Action::NEW only removes the button; EasyAdmin generates a route per CRUD action
     * regardless, so the page is refused here. An account that appears without a password nobody
     * received and without a consent record is worse than no account.
     */
    public function new(AdminContext $context): KeyValueStore|Response
    {
        throw $this->createNotFoundException('Accounts are created by the registration flow.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', t('admin.user.id'))->onlyOnIndex();

        yield TextField::new('email', t('account.email'))
            ->setDisabled()
            ->setHelp(t('admin.user.email.help'));

        yield TextField::new('contactName', t('account.contact_name'));
        yield ChoiceField::new('contactType', t('account.contact_type'));
        yield ChoiceField::new('contactWindow', t('account.contact_window'))->hideOnIndex();

        // Read-only: the column is a list of bare role constants and the entity has no setter, so
        // storing this list back from a form is not something the domain allows.
        yield ChoiceField::new('roles', t('admin.user.roles'))
            ->setTranslatableChoices(self::ROLE_KEYS)
            ->allowMultipleChoices()
            ->renderAsBadges()
            ->hideOnForm();

        // Written by the registration and confirmation flows, never by a form: no setters exist,
        // so these are read-only everywhere.
        yield DateTimeField::new('consentAt', t('admin.user.consent_at'))->onlyOnDetail();
        yield TextField::new('consentPolicyVersion', t('admin.user.consent_policy_version'))->onlyOnDetail();
        yield DateTimeField::new('adultConfirmedAt', t('admin.user.adult_confirmed_at'))->onlyOnDetail();
        yield DateTimeField::new('confirmedAt', t('admin.user.confirmed_at'))->hideOnForm();
        yield DateTimeField::new('createdAt', t('admin.user.created_at'))->hideOnForm();
    }
}
