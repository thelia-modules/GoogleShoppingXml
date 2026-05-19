<?php

namespace GoogleShoppingXml\Command;

use Exception;

use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlLogQuery;
use GoogleShoppingXml\Service\GoogleShoppingXmlService;
use GoogleShoppingXml\Service\Provider\LiaProductProvider;
use GoogleShoppingXml\Service\Provider\LiaSQLQueryService;
use GoogleShoppingXml\Service\XmlGenerator;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

use Thelia\Command\ContainerAwareCommand;
use Thelia\Core\Translation\Translator;


class GenerateLiaXmlFileCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("googleshopping:generateLiaXML")
            ->addOption('feed', 'f', InputArgument::OPTIONAL, 'Feed name')
            ->setDescription("Generate LIA XML file");
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initRequest();

        if (!(bool) GoogleShoppingXml::getConfigValue('lia_enabled', 0)) {
            $output->write('LIA feed generation is disabled. Enable it in the module configuration.', true);
            return 1;
        }

        if (!LiaSQLQueryService::isCompatible()) {
            $output->write('LIA feed requires column dealer_stock_config.google_merchant_store_id. Run the DB migration first.', true);
            return 1;
        }

        $feeds = GoogleshoppingxmlFeedQuery::create()->orderByLabel();

        if ($feedName = $input->getOption('feed')) {
            $feeds->filterByLabel($feedName);
        }

        $fs = new Filesystem();

        if (!$fs->exists(GoogleShoppingXmlService::XML_FILES_DIR)) {
            $fs->mkdir(GoogleShoppingXmlService::XML_FILES_DIR);
        }

        if (!$feed = $feeds->findOne()) {
            $output->write('No Feed found', true, $output::VERBOSITY_DEBUG);
            return 1;
        }

        try {
            $fileName = $feed->getLabel() . '_lia.xml';
            $filePath = GoogleShoppingXmlService::XML_FILES_DIR . $fileName;

            if ($fs->exists($filePath)) {
                $fs->remove($filePath);
            }

            $this->getContainer()
                ->get('googleshoppingxml.xmlGenerator')->export(
                    $this->getContainer()->get('googleshoppingxml.liaProductProvider')->getContent($feed),
                    $filePath,
                    $output
                );

        } catch (Exception $ex) {
            $output->write($ex->getMessage());
            $this->getContainer()
                ->get("googleshoppingxml.logger")->logFatal($feed, null, $ex->getMessage());
            return 1;
        }

        $this->getContainer()->get("googleshoppingxml.logger")->logSuccess($feed, null,
            Translator::getInstance()->trans(
                'The LIA XML file has been successfully generated.',
                [],
                GoogleShoppingXml::DOMAIN_NAME
            )
        );

        return 0;
    }
}
