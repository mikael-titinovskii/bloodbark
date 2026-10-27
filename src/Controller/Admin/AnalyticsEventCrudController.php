<?php

namespace App\Controller\Admin;

use App\Entity\AnalyticsEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

/** @extends AbstractCrudController<AnalyticsEvent> */
final class AnalyticsEventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AnalyticsEvent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('admin.analytics.event.singular'))
            ->setEntityLabelInPlural(t('admin.analytics.event.plural'))
            ->setDefaultSort(['occurredAt' => 'DESC'])
            ->setSearchFields(['name']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', t('admin.analytics.event.name')))
            ->add(DateTimeFilter::new('occurredAt', t('admin.analytics.event.occurred_at')));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', t('admin.analytics.event.id'))->onlyOnIndex();
        yield TextField::new('name', t('admin.analytics.event.name'));
        yield DateTimeField::new('occurredAt', t('admin.analytics.event.occurred_at'));
        yield TextareaField::new('properties', t('admin.analytics.event.properties'))
            ->formatValue(static fn (array $value): string => json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ))
            ->onlyOnDetail();
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
