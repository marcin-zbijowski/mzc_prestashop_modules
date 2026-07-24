<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Controller;

use MZConsulting\MzcProductFields\Repository\FieldRepository;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Backs this module's own category autocomplete widget (views/js/admin-field-form.js) —
 * core has no reusable "search categories for association" endpoint the way
 * it does for products (admin_products_search_products_for_association),
 * so this is this module's own, returning the same [{id,name,image}] shape
 * core's product search uses.
 */
class CategorySearchController extends FrameworkBundleAdminController
{
    public function searchAction(Request $request): JsonResponse
    {
        $query = (string) $request->get('query', '');
        $idLang = (int) $this->getContext()->language->id;

        $repository = $this->get(FieldRepository::class);
        $results = $repository->searchCategories($query, $idLang);

        return $this->json($results);
    }
}
