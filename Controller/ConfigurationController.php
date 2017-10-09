<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

class ConfigurationController extends BaseAdminController
{
    public function viewConfigAction($params = array())
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'GoogleShoppingXml', AccessManager::VIEW)) {
            return $response;
        }

        $fieldAssociationArray = GoogleshoppingxmlGoogleFieldAssociationQuery::create()->find()->toArray();

        return $this->render(
            "module-configuration",
            [
                'field_association_array' => $fieldAssociationArray
            ]
        );
    }

    public function getFeedXmlAction($feedId)
    {
        $feed = GoogleshoppingxmlFeedQuery::create()->findOneById($feedId);

        if ($feed == null) {
            return $this->pageNotFound();
        }

        $fieldAssociationArray = GoogleshoppingxmlGoogleFieldAssociationQuery::create()->find()->toArray();

        $args = [
            'feed_id' => $feedId,
            'feed_currency_id' => $feed->getCurrencyId(),
            'feed_lang_id' => $feed->getLangId(),
            'field_association_array' => $fieldAssociationArray
        ];

        return $this->render("xml/google_feed_rss_xml", $args);
    }
}
