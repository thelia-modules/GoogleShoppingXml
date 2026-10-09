<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Feed\FeedSettings;
use GoogleShoppingXml\Form\FeedSettingsForm;
use GoogleShoppingXml\GoogleShoppingXml;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;

#[Route('/admin/module/GoogleShoppingXml', name: 'googleshoppingxml.')]
class FeedSettingsController extends BaseAdminController
{
    #[Route('/feed-settings', name: 'feed_settings.save', methods: ['POST'])]
    public function save(UrlGeneratorInterface $urlGenerator): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['GoogleShoppingXml'], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(FeedSettingsForm::getName());
        $configurationUrl = $urlGenerator->generate('admin.module.configure', ['module_code' => 'GoogleShoppingXml', 'current_tab' => 'advanced']);

        try {
            $data = $this->validateForm($form)->getData();

            GoogleShoppingXml::setConfigValue(GoogleShoppingXml::EXCLUDE_OUT_OF_STOCK, $data['exclude_out_of_stock'] ? '1' : '0');
            GoogleShoppingXml::setConfigValue(GoogleShoppingXml::FEATURE_COLOR_IDS, self::idList($data['color_feature_ids'] ?? null));
            GoogleShoppingXml::setConfigValue(GoogleShoppingXml::FEATURE_GENDER_IDS, self::idList($data['gender_feature_ids'] ?? null));
            GoogleShoppingXml::setConfigValue(GoogleShoppingXml::FEATURE_MATERIAL_IDS, self::idList($data['material_feature_ids'] ?? null));
            GoogleShoppingXml::setConfigValue(GoogleShoppingXml::ATTRIBUTE_SIZE_IDS, self::idList($data['size_attribute_ids'] ?? null));
            GoogleShoppingXml::setConfigValue(GoogleShoppingXml::SUBTITLE_DETAIL, $data['subtitle_detail'] ? '1' : '0');
            GoogleShoppingXml::setConfigValue(GoogleShoppingXml::IMAGE_FILTER, trim((string) ($data['image_filter'] ?? '')) ?: GoogleShoppingXml::DEFAULT_IMAGE_FILTER);
        } catch (FormValidationException $exception) {
            $this->addFlash('danger', $this->createStandardFormValidationErrorMessage($exception));
        }

        return $this->generateRedirect($configurationUrl);
    }

    private static function idList(mixed $value): string
    {
        return implode(',', FeedSettings::parseIds((string) $value));
    }
}
