<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Form\FeedManagementForm;
use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountry;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountryQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

class FeedController extends BaseAdminController
{
    public function addFeedAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::CREATE)) {
            return $response;
        }

        $form = new FeedManagementForm($this->getRequest());

        try {
            $formData = $this->validateForm($form)->getData();

            $feed = new GoogleshoppingxmlFeed();

            $feed->setLabel($formData['feed_label'])
                ->setLangId($formData['lang_id'])
                ->setCurrencyId($formData['currency_id'])
                ->save();

            foreach ($formData['country_list_id'] as $country_id) {
                (new GoogleshoppingxmlFeedCountry())
                    ->setFeedId($feed->getId())
                    ->setCountryId($country_id)
                    ->save();
            }
        } catch (\Exception $e) {
            $message = null;
            $message = $e->getMessage();
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("GoogleShoppingXml configuration", [], GoogleShoppingXml::DOMAIN_NAME),
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

    public function updateFeedAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::UPDATE)) {
            return $response;
        }

        $form = new FeedManagementForm($this->getRequest());

        try {
            $formData = $this->validateForm($form)->getData();

            $feed = GoogleshoppingxmlFeedQuery::create()
                ->filterById($formData['id'])
                ->findOne();

            // Update field
            $feed->setLabel($formData['feed_label'])
                ->setLangId($formData['lang_id'])
                ->setCurrencyId($formData['currency_id'])
                ->save();

            // Delete country that are not in the provided list
            GoogleshoppingxmlFeedCountryQuery::create()
                ->filterByFeedId($formData['id'])
                ->filterByCountryId($formData['country_list_id'], Criteria::NOT_IN)
                ->delete();

            // Add new countries of the list if they don't already exist
            foreach ($formData['country_list_id'] as $country_id) {
                GoogleshoppingxmlFeedCountryQuery::create()
                    ->filterByFeedId($formData['id'])
                    ->filterByCountryId($country_id)
                    ->findOneOrCreate()
                    ->save();
            }
        } catch (\Exception $e) {
            $message = null;
            $message = $e->getMessage();
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("GoogleShoppingXml configuration", [], GoogleShoppingXml::DOMAIN_NAME),
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

    public function deleteFeedAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::DELETE)) {
            return $response;
        }

        $feed_id = $this->getRequest()->request->get('id_feed_to_delete');

        $feed = GoogleshoppingxmlFeedQuery::create()->findOneById($feed_id);
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
}
