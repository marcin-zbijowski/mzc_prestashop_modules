<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Controller;

use MZConsulting\MzcProductFields\Form\Type\BackgroundRuleType;
use MZConsulting\MzcProductFields\Repository\BackgroundRepository;
use MZConsulting\MzcProductFields\Repository\FontRepository;
use MZConsulting\MzcProductFields\Service\CombinationBackgroundResolver;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global "background per attribute value" rule management — see
 * Service/CombinationBackgroundResolver's docblock for why this lives on its
 * own screen rather than on each product's edit page: the same attribute
 * values (e.g. "Canvas", "30x40cm") are shared across many products in this
 * catalog, so a rule is configured once here and applies everywhere it's
 * relevant.
 */
class BackgroundRuleController extends FrameworkBundleAdminController
{
    public function indexAction(Request $request): Response
    {
        $resolver = $this->get(CombinationBackgroundResolver::class);
        $backgroundRepository = $this->get(BackgroundRepository::class);
        $fontRepository = $this->get(FontRepository::class);
        $idLang = (int) $this->getContext()->language->id;

        // `edit_id` is carried in the query string on both the initial GET
        // (to load the rule's current values into the form) and the later
        // POST (via the form's own `action` URL below) — that's how we still
        // know, once the form comes back submitted, which existing row to
        // update rather than blindly inserting a new one. See
        // CombinationBackgroundResolver::updateRule()'s docblock for why a
        // plain re-submit (relying on the attribute-id key alone) isn't
        // enough once the merchant can change the attribute selection while
        // editing.
        $editId = (int) $request->query->get('edit_id');
        $editingRule = $editId > 0 ? $resolver->getRuleById($editId) : null;
        if ($editId > 0 && $editingRule === null) {
            $this->addFlash('error', $this->trans('That rule no longer exists.', 'Modules.Mzcproductfields.Admin'));
            $editId = 0;
        }

        $formData = $editingRule ? [
            'attribute_ids' => $editingRule['attribute_ids'],
            'id_background' => $editingRule['id_background'],
            'font' => $editingRule['font'],
            'font_size' => $editingRule['font_size'],
            'pos_y' => $editingRule['pos_y'],
        ] : null;

        $form = $this->createForm(BackgroundRuleType::class, $formData, [
            'attribute_value_choices' => $resolver->getAttributeValueChoices($idLang),
            'background_choices' => $backgroundRepository->getChoices(),
            'font_choices' => $fontRepository->getChoices(),
            'action' => $this->generateUrl(
                'mzc_productfields_background_rule_index',
                $editId > 0 ? ['edit_id' => $editId] : []
            ),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $font = $data['font'] ?: null;
            $fontSize = $data['font_size'] !== null && $data['font_size'] !== '' ? (float) $data['font_size'] : null;
            $posY = $data['pos_y'] !== null && $data['pos_y'] !== '' ? (float) $data['pos_y'] : null;

            $ok = $editId > 0
                ? $resolver->updateRule($editId, (array) $data['attribute_ids'], (int) $data['id_background'], $font, $fontSize, $posY)
                : $resolver->saveRule((array) $data['attribute_ids'], (int) $data['id_background'], $font, $fontSize, $posY);

            if ($ok) {
                $this->addFlash('success', $this->trans('Rule saved.', 'Modules.Mzcproductfields.Admin'));
            } else {
                $this->addFlash('error', $this->trans('Could not save rule.', 'Modules.Mzcproductfields.Admin'));
            }

            return $this->redirectToRoute('mzc_productfields_background_rule_index');
        }

        return $this->render('@Modules/mzcproductfields/views/templates/admin/background_rule/index.html.twig', [
            'rules' => $resolver->getAllRules($idLang),
            'mzc_background_rule_form' => $form->createView(),
            'manage_backgrounds_url' => $this->generateUrl('mzc_productfields_background_index'),
            'is_editing' => $editId > 0,
        ]);
    }

    public function deleteAction(Request $request, int $id): Response
    {
        $resolver = $this->get(CombinationBackgroundResolver::class);

        if ($resolver->deleteRule($id)) {
            $this->addFlash('success', $this->trans('Rule deleted.', 'Modules.Mzcproductfields.Admin'));
        } else {
            $this->addFlash('error', $this->trans('Could not delete rule.', 'Modules.Mzcproductfields.Admin'));
        }

        return $this->redirectToRoute('mzc_productfields_background_rule_index');
    }
}
