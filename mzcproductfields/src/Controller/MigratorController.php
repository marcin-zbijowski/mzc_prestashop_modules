<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Controller;

use MZConsulting\MzcProductFields\Service\AnvantoMigrator;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anvanto migrator screen (plan section 9) — two independent, re-runnable
 * actions: archiving historical order data (must run before Anvanto is ever
 * uninstalled) and migrating field configuration one category at a time (the
 * plan's "pilot category first" requirement, to bound blast radius rather
 * than migrating the whole catalog in one pass).
 */
class MigratorController extends FrameworkBundleAdminController
{
    public function indexAction(Request $request): Response
    {
        $migrator = $this->get(AnvantoMigrator::class);

        return $this->render('@Modules/mzcproductfields/views/templates/admin/migrator/index.html.twig', [
            'anvanto_available' => $migrator->isAnvantoDataAvailable(),
            'category_search_url' => $this->generateUrl('mzc_productfields_category_search', [
                'query' => '__QUERY__',
            ]),
        ]);
    }

    public function archiveOrdersAction(Request $request): Response
    {
        $migrator = $this->get(AnvantoMigrator::class);
        $result = $migrator->archiveOrders();

        if (!$result['available']) {
            $this->addFlash('error', $this->trans('No Anvanto order data found on this environment.', 'Modules.Mzcproductfields.Admin'));
        } else {
            $this->addFlash('success', $this->trans(
                'Archived %inserted% order row(s), skipped %skipped% already-archived (of %total% total).',
                'Modules.Mzcproductfields.Admin',
                [
                    '%inserted%' => $result['inserted'],
                    '%skipped%' => $result['skipped'],
                    '%total%' => $result['total_source'],
                ]
            ));
        }

        return $this->redirectToRoute('mzc_productfields_migrator_index');
    }

    public function migrateFieldConfigAction(Request $request): Response
    {
        $idCategory = (int) $request->request->get('id_category');

        if ($idCategory <= 0) {
            $this->addFlash('error', $this->trans('Please enter a valid category id.', 'Modules.Mzcproductfields.Admin'));

            return $this->redirectToRoute('mzc_productfields_migrator_index');
        }

        $migrator = $this->get(AnvantoMigrator::class);
        $result = $migrator->migrateFieldConfig($idCategory);

        if (!$result['available']) {
            $this->addFlash('error', $this->trans('No Anvanto field data found on this environment.', 'Modules.Mzcproductfields.Admin'));

            return $this->redirectToRoute('mzc_productfields_migrator_index');
        }

        $this->addFlash('success', $this->trans(
            'Category "%category%": created %created% field(s), skipped %skipped% already migrated.',
            'Modules.Mzcproductfields.Admin',
            [
                '%category%' => $result['category_name'],
                '%created%' => $result['created'],
                '%skipped%' => $result['skipped_existing'],
            ]
        ));

        foreach ($result['warnings'] as $warning) {
            $this->addFlash('warning', $warning);
        }

        return $this->redirectToRoute('mzc_productfields_migrator_index');
    }
}
