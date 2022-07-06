<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Form\FeedManagementForm;
use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;

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
        $form = $this->createForm(FeedManagementForm::getName());

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

        } catch (\Exception $e) {
            $message = null;
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

    public function deleteFeedAction(Request $request)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::DELETE)) {
            return $response;
        }

        $feedId = $request->get('id_feed_to_delete');

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
}
