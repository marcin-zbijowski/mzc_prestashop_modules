<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Controller;

use MZConsulting\MzcProductFields\Form\Type\FontUploadType;
use MZConsulting\MzcProductFields\Repository\FontRepository;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Font management screen — upload custom font files, made available in the
 * "Preview font" dropdown on the field CRUD screen (see FieldType).
 */
class FontController extends FrameworkBundleAdminController
{
    public function indexAction(Request $request): Response
    {
        $repository = $this->get(FontRepository::class);

        $form = $this->createForm(FontUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $error = $repository->upload(
                (string) $form->get('name')->getData(),
                $form->get('file')->getData()
            );

            if ($error === null) {
                $this->addFlash('success', $this->trans('Font uploaded.', 'Modules.Mzcproductfields.Admin'));

                return $this->redirectToRoute('mzc_productfields_font_index');
            }

            $this->addFlash('error', $error);
        }

        return $this->render('@Modules/mzcproductfields/views/templates/admin/font/index.html.twig', [
            'fonts' => $repository->findAllForGrid(),
            'mzc_font_form' => $form->createView(),
        ]);
    }

    public function deleteAction(Request $request, int $id): Response
    {
        $repository = $this->get(FontRepository::class);

        if ($repository->delete($id)) {
            $this->addFlash('success', $this->trans('Font deleted.', 'Modules.Mzcproductfields.Admin'));
        } else {
            $this->addFlash('error', $this->trans('Could not delete font.', 'Modules.Mzcproductfields.Admin'));
        }

        return $this->redirectToRoute('mzc_productfields_font_index');
    }
}
