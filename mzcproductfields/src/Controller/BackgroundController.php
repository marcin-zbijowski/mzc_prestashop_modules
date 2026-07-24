<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Controller;

use MZConsulting\MzcProductFields\Form\Type\BackgroundUploadType;
use MZConsulting\MzcProductFields\Repository\BackgroundRepository;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Background image management screen — upload the images (e.g. "Front",
 * "Back") a field's live preview can be anchored to instead of the theme's
 * own product photo (see FieldType / ProductFieldsPresenter). Mirrors
 * FontController.
 */
class BackgroundController extends FrameworkBundleAdminController
{
    public function indexAction(Request $request): Response
    {
        $repository = $this->get(BackgroundRepository::class);

        $form = $this->createForm(BackgroundUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $error = $repository->upload(
                (string) $form->get('name')->getData(),
                $form->get('file')->getData()
            );

            if ($error === null) {
                $this->addFlash('success', $this->trans('Background image uploaded.', 'Modules.Mzcproductfields.Admin'));

                return $this->redirectToRoute('mzc_productfields_background_index');
            }

            $this->addFlash('error', $error);
        }

        return $this->render('@Modules/mzcproductfields/views/templates/admin/background/index.html.twig', [
            'backgrounds' => $repository->findAllForGrid(),
            'mzc_background_form' => $form->createView(),
        ]);
    }

    public function deleteAction(Request $request, int $id): Response
    {
        $repository = $this->get(BackgroundRepository::class);

        if ($repository->delete($id)) {
            $this->addFlash('success', $this->trans('Background image deleted.', 'Modules.Mzcproductfields.Admin'));
        } else {
            $this->addFlash('error', $this->trans('Could not delete background image.', 'Modules.Mzcproductfields.Admin'));
        }

        return $this->redirectToRoute('mzc_productfields_background_index');
    }
}
