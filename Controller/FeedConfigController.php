<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Form\FeedManagementForm;
use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountry;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountryQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlLogQuery;
use GoogleShoppingXml\Service\GoogleShoppingXmlService;
use GoogleShoppingXml\Service\Provider\ProductProvider;
use GoogleShoppingXml\Service\XmlGenerator;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Symfony\Component\Filesystem\Filesystem;

class FeedConfigController extends BaseAdminController
{
    public function addFeedAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::CREATE)) {
            return $response;
        }

        return $this->addOrUpdateFeed();
    }

    public function updateFeedAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::UPDATE)) {
            return $response;
        }

        return $this->addOrUpdateFeed();
    }

    protected function addOrUpdateFeed()
    {
        $form = $this->createForm(FeedManagementForm::class);

        try {
            $formData = $this->validateForm($form)->getData();

            $feed = GoogleshoppingxmlFeedQuery::create()
                ->filterById($formData['id'])
                ->findOneOrCreate();

            $feed->setLabel($formData['feed_label'])
                ->setLangId($formData['lang_id'])
                ->setCurrencyId($formData['currency_id'])
                ->setCountryId($formData['country_id'])
                ->save();

            $this->saveShippingCountries($feed->getId(), $formData['shipping_country_ids'] ?? []);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("GoogleShoppingXml configuration", [], GoogleShoppingXml::DOMAIN_NAME),
                $message,
                $form,
                $e
            );
        }

        return $this->generateRedirectFromRoute(
            "admin.module.configure",
            array(),
            array(
                'module_code' => 'GoogleShoppingXml',
                'current_tab' => 'feeds'
            )
        );
    }

    /**
     * Countries this feed advertises delivery to. Replaced wholesale: the form always posts the
     * complete selection, and an empty one means "every country a live carrier serves".
     *
     * @param int   $feedId
     * @param int[] $countryIds
     */
    protected function saveShippingCountries($feedId, array $countryIds)
    {
        GoogleshoppingxmlFeedCountryQuery::create()
            ->filterByFeedId($feedId)
            ->delete();

        foreach (array_unique($countryIds) as $countryId) {
            (new GoogleshoppingxmlFeedCountry())
                ->setFeedId($feedId)
                ->setCountryId($countryId)
                ->save();
        }
    }

    public function deleteFeedAction(Request $request)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::DELETE)) {
            return $response;
        }

        $feedId = $request->request->get('id_feed_to_delete');

        $feed = GoogleshoppingxmlFeedQuery::create()->findOneById($feedId);
        if ($feed != null) {
            $feed->delete();
        }

        return $this->generateRedirectFromRoute(
            "admin.module.configure",
            array(),
            array(
                'module_code' => 'GoogleShoppingXml',
                'current_tab' => 'feeds'
            )
        );
    }



    public function generateFeedXmlAction($feedId, ProductProvider $productProviderService, XmlGenerator $xmlGenerator)
    {
        $this->logger = GoogleshoppingxmlLogQuery::create();
        $feed = GoogleshoppingxmlFeedQuery::create()->findOneById($feedId);

        if ($feed == null) {
            $this->pageNotFound();
        }

        $fs = new Filesystem();

        if (!$fs->exists(GoogleShoppingXmlService::XML_FILES_DIR)) {
            $fs->mkdir(GoogleShoppingXmlService::XML_FILES_DIR);
        }

        try {
            $fileName = $feed->getLabel() . '.xml';
            $filePath = GoogleShoppingXmlService::XML_FILES_DIR . $fileName;

            if ($fs->exists($filePath)) {
                $fs->remove($filePath);
            }

            $xmlGenerator->export($productProviderService->getContent($feed), $filePath);

        } catch (Exception $ex) {
            $this->logger->logFatal($feed, null, $ex->getMessage());
        }

        $this->logger->logSuccess($feed, null,
            Translator::getInstance()->trans(
                'The XML file has been successfully generated.',
                [],
                GoogleShoppingXml::DOMAIN_NAME
            )
        );

        return $this->generateRedirectFromRoute(
            "admin.module.configure",
            array(),
            array(
                'module_code' => 'GoogleShoppingXml',
                'current_tab' => 'feeds'
            )
        );
    }
}
