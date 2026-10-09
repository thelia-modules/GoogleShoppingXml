<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Form\ExclusionForm;
use GoogleShoppingXml\Service\ExclusionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;

#[Route('/admin/module/GoogleShoppingXml', name: 'googleshoppingxml.')]
class ExclusionController extends BaseAdminController
{
    /**
     * Every combination of the product is written: the boxes checked are excluded, the others
     * are back in the feed.
     */
    #[Route('/product/{productId}/exclusions', name: 'product.exclusions', methods: ['POST'], requirements: ['productId' => '\d+'])]
    public function saveExclusions(int $productId, ExclusionRepository $exclusionRepository, UrlGeneratorInterface $urlGenerator): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::PRODUCT, [], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(ExclusionForm::class);
        $fallbackUrl = $urlGenerator->generate('admin.products.update', ['product_id' => $productId, 'current_tab' => 'modules']);

        try {
            $data = $this->validateForm($form)->getData();
            $excluded = array_map('intval', (array) ($data['excluded_combinations'] ?? []));

            $flags = [];
            foreach ($exclusionRepository->combinationIdsOfProduct($productId) as $combinationId) {
                $flags[$combinationId] = \in_array($combinationId, $excluded, true);
            }
            $exclusionRepository->save($flags);

            return $this->generateSuccessRedirect($form) ?? $this->generateRedirect($fallbackUrl);
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                $this->translator->trans('Google Shopping exclusions', [], GoogleShoppingXml::DOMAIN_NAME),
                $this->createStandardFormValidationErrorMessage($exception),
                $form,
                $exception,
            );
            $this->addFlash('danger', $this->createStandardFormValidationErrorMessage($exception));
        }

        return $this->generateErrorRedirect($form) ?? $this->generateRedirect($fallbackUrl);
    }
}
