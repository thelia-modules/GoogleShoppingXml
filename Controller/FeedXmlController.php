<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlLogQuery;
use GoogleShoppingXml\Service\GoogleShoppingXmlService;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Controller\Front\BaseFrontController;

class FeedXmlController extends BaseFrontController
{
    /**
     * @var GoogleshoppingxmlLogQuery $logger
     */
    private $logger;

    private $ean_rule;

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

        if ($feed == null) {
            $this->pageNotFound();
        }
        $fs = new Filesystem();

        try {
            if (!$fs->exists(GoogleShoppingXmlService::XML_FILES_DIR)) {
                $fs->mkdir(GoogleShoppingXmlService::XML_FILES_DIR);
            }

            $feedXml = GoogleShoppingXmlService::XML_FILES_DIR . $feed->getLabel() . '.xml';

            if (!$fs->exists($feedXml)) {
                $this->nullResponse();
            }

            $response = new Response();
            $response->setContent(file_get_contents($feedXml));
            $response->headers->set('Content-Type', 'application/xml');

            return $response;

        } catch (\Exception $ex) {
            $this->logger->logFatal($feed, null, $ex->getMessage(), $ex->getFile() . " at line " . $ex->getLine());
            throw $ex;
        }
    }
}
