<?php

namespace GoogleShoppingXml\Command;

use Exception;

use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlLogQuery;
use GoogleShoppingXml\Service\GoogleShoppingXmlService;
use GoogleShoppingXml\Service\Provider\ProductProvider;
use GoogleShoppingXml\Service\XmlGenerator;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

use Thelia\Command\ContainerAwareCommand;
use Thelia\Core\Translation\Translator;

class GenerateXmlFileCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("googleshopping:generateXML")
            ->addOption('feed', 'f', InputArgument::OPTIONAL, 'Feed name')
            ->addArgument(
                "optimised-mode",
                InputArgument::OPTIONAL,
                "Choose generation mode, optimised or legacy, optimised mode need the CTE availability in your SGBD",
                true
            )
            ->setDescription("Generate XML file");
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initRequest();

        $feeds = GoogleshoppingxmlFeedQuery::create();

        if ($feedName = $input->getOption('feed')) {
            $feeds->filterByLabel($feedName);
        }

        $fs = new Filesystem();

        if (!$fs->exists(GoogleShoppingXmlService::XML_FILES_DIR)) {
            $fs->mkdir(GoogleShoppingXmlService::XML_FILES_DIR);
        }

        if (!$feed = $feeds->findOne()) {
            $output->write('No Feed found', true, $output::VERBOSITY_DEBUG);
        }

        if (true === $input->getArgument('optimised-mode')) {
            try {
                $fileName = $feed->getLabel() . '.xml';
                $filePath = GoogleShoppingXmlService::XML_FILES_DIR . $fileName;

                if ($fs->exists($filePath)) {
                    $fs->remove($filePath);
                }

                $this->getContainer()
                    ->get('googleshoppingxml.xmlGenerator')->export(
                        $this->getContainer()->get('googleshoppingxml.productProvider')->getContent($feed),
                        $filePath,
                        $output
                    );

            } catch (Exception $ex) {
                $output->write($ex->getMessage());
                $this->getContainer()
                    ->get("googleshoppingxml.logger")->logFatal($feed, null, $ex->getMessage());
            }

            $this->getContainer()->get("googleshoppingxml.logger")->logSuccess($feed, null,
                Translator::getInstance()->trans(
                    'The XML file has been successfully generated.',
                    [],
                    GoogleShoppingXml::DOMAIN_NAME
                )
            );

            return 1;
        }

        //OH BOY !
        $this->executeLegacyGeneration($fs, $feeds->find());

        return 1;
    }

    /**
     * @param Filesystem $fs
     * @param $feeds
     * @return void
     * @throws Exception
     * @deprecated Uggly, horrible, sooo long, don't use it!! Update mysql and use executeOptimisedGeneration
     */
    private function executeLegacyGeneration(Filesystem $fs, $feeds)
    {
        /** @var GoogleShoppingXmlService $googleShoppingXmlService */
        $googleShoppingXmlService = $this->getContainer()->get('googleshoppingxml.service');

        /** @var GoogleshoppingxmlFeed $feed */
        foreach ($feeds as $feed) {
            $content = $googleShoppingXmlService->getFeedXmlAction($feed->getId());

            $fileName = $feed->getLabel() . '.xml';

            if ($fs->exists(GoogleShoppingXmlService::XML_FILES_DIR . $fileName)) {
                $fs->remove(GoogleShoppingXmlService::XML_FILES_DIR . $fileName);
            }

            $fs->dumpFile(GoogleShoppingXmlService::XML_FILES_DIR . $fileName, $content);
        }
    }
}