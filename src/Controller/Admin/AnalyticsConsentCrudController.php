<?php

namespace App\Controller\Admin;

use App\Entity\AnalyticsConsent;
use App\Service\Analytics\AnalyticsConsentService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function Symfony\Component\Translation\t;

/** @extends AbstractCrudController<AnalyticsConsent> */
final class AnalyticsConsentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AnalyticsConsentService $consents,
        private readonly CsrfTokenManagerInterface $csrfTokens,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AnalyticsConsent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('admin.analytics.consent.singular'))
            ->setEntityLabelInPlural(t('admin.analytics.consent.plural'))
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->setSearchFields(['consentId']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $forget = Action::new('forget', t('admin.analytics.consent.forget'), 'fa fa-user-slash')
            ->linkToRoute('app_admin_analytics_consent_forget', fn (AnalyticsConsent $consent): array => [
                'id' => $consent->getId(),
                'token' => $this->csrfTokens->getToken('analytics_forget_'.$consent->getId())->getValue(),
            ])
            ->renderAsForm();

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $forget)
            ->add(Crud::PAGE_DETAIL, $forget)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('consentId', t('admin.analytics.consent.id')))
            ->add(DateTimeFilter::new('updatedAt', t('admin.analytics.consent.updated_at')));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', t('admin.analytics.event.id'))->onlyOnIndex();
        yield TextField::new('consentId', t('admin.analytics.consent.id'));
        yield BooleanField::new('analyticsAllowed', t('admin.analytics.consent.allowed'));
        yield TextField::new('revision', t('admin.analytics.consent.revision'))->onlyOnDetail();
        yield DateTimeField::new('createdAt', t('admin.analytics.consent.created_at'))->onlyOnDetail();
        yield DateTimeField::new('updatedAt', t('admin.analytics.consent.updated_at'));
    }

    #[AdminRoute('/{id}/forget', name: 'forget', options: ['methods' => ['POST']])]
    public function forget(AnalyticsConsent $consent, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if (!$this->isCsrfTokenValid('analytics_forget_'.$consent->getId(), $request->query->getString('token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->consents->forget($consent);
        $this->addFlash('success', t('admin.analytics.consent.forgotten'));

        return $this->redirectToRoute('app_admin_analytics_consent_index');
    }

    public function new(AdminContext $context): KeyValueStore|Response
    {
        throw $this->createNotFoundException();
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        throw $this->createNotFoundException();
    }

    public function delete(AdminContext $context): KeyValueStore|Response
    {
        throw $this->createNotFoundException();
    }
}
