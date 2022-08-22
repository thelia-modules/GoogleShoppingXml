<?php

namespace GoogleShoppingXml\Command;

use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Service\GoogleShoppingXmlService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Model\ProductSaleElementsQuery;

class GenerateXmlFileCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("googleshopping:generateXML")
            ->addOption('feed', 'f', InputArgument::OPTIONAL, 'Feed name', null)
            ->setDescription("Generate XML file");
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initRequest();
        $feeds = GoogleshoppingxmlFeedQuery::create();
        if ($feedName = $input->getOption('feed')){
            $feeds->filterByLabel($feedName);
        }
        $feeds->find();
        $fs = new Filesystem();

        if (!$fs->exists(GoogleShoppingXmlService::XML_FILES_DIR)) {
            $fs->mkdir(GoogleShoppingXmlService::XML_FILES_DIR);
        }

        /** @var GoogleShoppingXmlService $googleShoppingXmlService */
        $googleShoppingXmlService = $this->getContainer()->get('googleshoppingxml.service');

        /** @var GoogleshoppingxmlFeed $feed */
        foreach ($feeds as $feed) {
            $content = $googleShoppingXmlService->getFeedXmlAction($feed->getId());

            $fileName = $feed->getLabel().'.xml';
            if ($fs->exists(GoogleShoppingXmlService::XML_FILES_DIR.$fileName)) {
                $fs->remove(GoogleShoppingXmlService::XML_FILES_DIR.$fileName);
            }
            $fs->dumpFile(GoogleShoppingXmlService::XML_FILES_DIR.$fileName, $content);
        }

    }



}