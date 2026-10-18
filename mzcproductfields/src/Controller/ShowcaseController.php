<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Controller;

use Module;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Saves which products the home page's live-personalisation band offers.
 *
 * The list screen posts the ids as JSON in one hidden input (the same picker the field
 * form uses). All the rules — casting, deduplication, the limit, what an empty list
 * means — live in the module's savePinnedShowcaseProducts(), so this stays a thin
 * adapter. Like CategorySearchController it is not a registered service.
 */
class ShowcaseController extends FrameworkBundleAdminController
{
    public function saveAction(Request $request): RedirectResponse
    {
        /** @var \Mzcproductfields|false|null $module */
        $module = Module::getInstanceByName('mzcproductfields');

        if (!$module) {
            $this->addFlash('error', $this->trans('Could not save the home page products.', 'Modules.Mzcproductfields.Admin'));

            return $this->redirectToRoute('mzc_productfields_field_index');
        }

        $decoded = json_decode((string) $request->request->get('showcase_products', '[]'), true);
        $saved = $module->savePinnedShowcaseProducts(is_array($decoded) ? $decoded : []);

        $this->addFlash(
            'success',
            $saved
                ? $this->trans('Home page products saved.', 'Modules.Mzcproductfields.Admin')
                : $this->trans('Home page products cleared — the section picks them from the Home category again.', 'Modules.Mzcproductfields.Admin')
        );

        return $this->redirectToRoute('mzc_productfields_field_index');
    }
}
