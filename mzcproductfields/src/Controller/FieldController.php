<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Controller;

use MZConsulting\MzcProductFields\Form\Type\FieldType;
use MZConsulting\MzcProductFields\Repository\FieldRepository;
use MZConsulting\MzcProductFields\Repository\FontRepository;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Mzcproductfields;
use TaxRulesGroup;

/**
 * Field management screen (a) from the plan — CRUD for personalization field
 * definitions, independent of any single product's edit page. This is the
 * only place scope (all products / one category / one product) is set, so
 * assigning a field to a whole category never requires touching individual
 * product cards.
 *
 * Built on FrameworkBundleAdminController (not the newer PrestaShopAdminController)
 * because PrestaShopAdminController only exists in PS 9.x core — confirmed absent
 * from the PS 8.2.7 codebase this module must also run on. FrameworkBundleAdminController
 * exists in both (deprecated since 9.0 but not removed until 10+), so it's the only
 * base that avoids a version_compare branch here.
 */
class FieldController extends FrameworkBundleAdminController
{
    public function indexAction(Request $request): Response
    {
        $repository = $this->get(FieldRepository::class);
        $idLang = (int) $this->getContext()->language->id;

        return $this->render('@Modules/mzcproductfields/views/templates/admin/field/index.html.twig', [
            'fields' => $repository->findAllForGrid($idLang),
            'showcase' => $this->getShowcaseViewData($repository, $idLang),
        ]);
    }

    public function createAction(Request $request): Response
    {
        return $this->handleForm($request, 0);
    }

    public function editAction(Request $request, int $id): Response
    {
        return $this->handleForm($request, $id);
    }

    public function deleteAction(Request $request, int $id): Response
    {
        $repository = $this->get(FieldRepository::class);

        if ($repository->delete($id)) {
            $this->addFlash('success', $this->trans('Field deleted.', 'Modules.Mzcproductfields.Admin'));
        } else {
            $this->addFlash('error', $this->trans('Could not delete field.', 'Modules.Mzcproductfields.Admin'));
        }

        return $this->redirectToRoute('mzc_productfields_field_index');
    }

    private function handleForm(Request $request, int $id): Response
    {
        $repository = $this->get(FieldRepository::class);
        $data = $repository->getFormData($id);

        $fontRepository = $this->get(FontRepository::class);
        $form = $this->createForm(FieldType::class, $data, [
            'tax_rules_groups_choices' => $this->getTaxRulesGroupsChoices(),
            'font_choices' => $fontRepository->getChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $repository->save($form->getData());
                $this->addFlash('success', $this->trans('Field saved.', 'Modules.Mzcproductfields.Admin'));

                return $this->redirectToRoute('mzc_productfields_field_index');
            } catch (\PrestaShopException $e) {
                // Defense in depth: the form-level constraints above should already
                // catch invalid data, but a legacy ObjectModel validation error must
                // never surface as a raw fatal error page.
                $this->addFlash('error', $this->trans('Could not save field.', 'Modules.Mzcproductfields.Admin') . ' ' . $e->getMessage());
            }
        }

        return $this->render('@Modules/mzcproductfields/views/templates/admin/field/form.html.twig', [
            'mzc_field_form' => $form->createView(),
            'is_edit' => $id > 0,
            'prefill' => $repository->getPrefillData($id),
            'product_search_url' => $this->generateUrl('admin_products_search_products_for_association', [
                'languageCode' => $this->getContext()->language->iso_code,
                'query' => '__QUERY__',
            ]),
            'category_search_url' => $this->generateUrl('mzc_productfields_category_search', [
                'query' => '__QUERY__',
            ]),
        ]);
    }

    /**
     * What the "products in the home page section" panel needs: the pinned products,
     * each with the reason it would not be shown (if any), and the limit.
     *
     * The reasons come from the module itself, the same test the home page widget
     * applies, so the warning here can never disagree with what the visitor sees.
     *
     * @return array{limit:int,entries:array<int,array<string,mixed>>,search_url:string}
     */
    private function getShowcaseViewData(FieldRepository $repository, int $idLang): array
    {
        $module = \Module::getInstanceByName('mzcproductfields');

        $messages = [
            'not_found' => $this->trans('This product no longer exists.', 'Modules.Mzcproductfields.Admin'),
            'no_front_field' => $this->trans('No text field for the front applies to this product, so it will not be shown.', 'Modules.Mzcproductfields.Admin'),
            'inactive' => $this->trans('This product is disabled, so it will not be shown.', 'Modules.Mzcproductfields.Admin'),
            'no_cover' => $this->trans('This product has no photo, so it will not be shown.', 'Modules.Mzcproductfields.Admin'),
        ];

        $entries = [];
        if ($module) {
            foreach ($repository->getProductEntries($module->getPinnedShowcaseProducts(), $idLang) as $entry) {
                $reason = $module->getShowcaseRejection($entry['id']);
                $entry['warning'] = $reason !== null ? ($messages[$reason] ?? $reason) : '';
                $entries[] = $entry;
            }
        }

        return [
            'limit' => $module ? (int) Mzcproductfields::SHOWCASE_LIMIT : 0,
            'entries' => $entries,
            'search_url' => $this->generateUrl('admin_products_search_products_for_association', [
                'languageCode' => $this->getContext()->language->iso_code,
                'query' => '__QUERY__',
            ]),
        ];
    }

    /**
     * @return array<int,string> id_tax_rules_group => label, plus a "no tax" option
     */
    private function getTaxRulesGroupsChoices(): array
    {
        $choices = [0 => $this->trans('No tax', 'Modules.Mzcproductfields.Admin')];
        foreach (TaxRulesGroup::getTaxRulesGroups(true) as $group) {
            $choices[(int) $group['id_tax_rules_group']] = $group['name'];
        }

        return $choices;
    }
}
