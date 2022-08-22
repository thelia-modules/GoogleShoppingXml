<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Events\AdditionalFieldEvent;
use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociation;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlLogQuery;
use GoogleShoppingXml\Service\GoogleShoppingXmlService;
use GoogleShoppingXml\Tools\GtinChecker;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Action\Image;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleQuery;
use Thelia\Module\BaseModule;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\URL;

class FeedXmlController extends BaseFrontController
{
    /**
     * @var GoogleshoppingxmlLogQuery $logger
     */
    private $logger;

    private $ean_rule;

    private $nb_pse;
    private $nb_pse_invisible;
    private $nb_pse_error;

    const EAN_RULE_ALL = "all";
    const EAN_RULE_CHECK_FLEXIBLE = "check_flexible";
    const EAN_RULE_CHECK_STRICT = "check_strict";
    const EAN_RULE_NONE = "none";

    const DEFAULT_EAN_RULE = self::EAN_RULE_CHECK_STRICT;

    public function getFeedXmlAction($feedId)
    {
        $this->logger = GoogleshoppingxmlLogQuery::create();
        $this->ean_rule = GoogleShoppingXml::getConfigValue("ean_rule", self::DEFAULT_EAN_RULE);

        $feed = GoogleshoppingxmlFeedQuery::create()->findOneById($feedId);

        $request = $this->getRequest();

        $limit = $request->get('limit', null);
        $offset = $request->get('offset', null);

        if ($feed == null) {
            $this->pageNotFound();
        }
        $fs = new Filesystem();

        try {
            if (!$fs->exists(GoogleShoppingXmlService::XML_FILES_DIR)) {
                $fs->mkdir(GoogleShoppingXmlService::XML_FILES_DIR);
            }

            $feedXml = GoogleShoppingXmlService::XML_FILES_DIR.$feed->getLabel().'.xml';

            if (!$fs->exists($feedXml)) {
                /** @var GoogleShoppingXmlService $googleShoppingXmlService */
                $googleShoppingXmlService = $this->getContainer()->get('googleshoppingxml.service');
                $content = $googleShoppingXmlService->getFeedXmlAction($feedId, $limit, $offset);
                $fs->dumpFile($feedXml, $content);
            }

            $response = new Response();
            $response->setContent(file_get_contents($feedXml));
            $response->headers->set('Content-Type', 'application/xml');

            return $response;

        } catch (\Exception $ex) {
            $this->logger->logFatal($feed, null, $ex->getMessage(), $ex->getFile()." at line ".$ex->getLine());
            throw $ex;
        }
    }
}
