<?php

namespace GoogleShoppingXml\Command;

use GoogleShoppingXml\Exception\FeedGenerationException;
use GoogleShoppingXml\Feed\FeedGenerator;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Service\GoogleShoppingXmlService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Command\ContainerAwareCommand;

/**
 * Generates the feeds: the one named by --feed (its label, or its id), or every feed. Each feed is
 * generated in its own language, so its links and images are on the domain of that language when the
 * shop has one domain per language. Exit code 1 when a feed is unknown or could not be generated: its
 * previous file is still served.
 */
class GenerateXmlFileCommand extends ContainerAwareCommand
{
    public function __construct(private readonly FeedGenerator $feedGenerator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('googleshopping:generateXML')
            ->addOption('feed', 'f', InputOption::VALUE_REQUIRED, 'Label (quoted when it holds spaces) or id of the feed; every feed when omitted')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, '[TESTING] Number of combinations read')
            ->addArgument(
                'optimised-mode',
                InputArgument::OPTIONAL,
                'Generation mode: "legacy" (or 0, false) for the deprecated generator, the current one otherwise',
                'optimised'
            )
            ->setDescription('Generate the XML files of the Google Shopping feeds');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $feeds = $this->feeds($input->getOption('feed'));
        if ([] === $feeds) {
            $output->writeln(sprintf('<error>%s</error>', FeedGenerationException::unknownFeed((string) $input->getOption('feed'))->getMessage()));

            return Command::FAILURE;
        }

        if (\in_array(strtolower((string) $input->getArgument('optimised-mode')), ['legacy', '0', 'false'], true)) {
            $this->initRequest();
            $this->executeLegacyGeneration(new Filesystem(), $feeds);

            return Command::SUCCESS;
        }

        $limit = filter_var($input->getOption('limit'), \FILTER_VALIDATE_INT);
        $failed = false;

        foreach ($feeds as $feed) {
            $this->initRequest($feed->getLang());

            try {
                $written = $this->feedGenerator->generate($feed, null, false !== $limit && $limit > 0 ? $limit : null);
                $output->writeln(null === $written
                    ? sprintf('<comment>%s: another generation is running, nothing done.</comment>', $feed->getLabel())
                    : sprintf('%s: %d items, %s', $feed->getLabel(), $written, FeedGenerator::pathOf($feed)));
            } catch (FeedGenerationException $exception) {
                $output->writeln(sprintf('<error>%s: %s</error>', $feed->getLabel(), $exception->getMessage()));
                $failed = true;
            } finally {
                $this->getContainer()->get('request_stack')?->pop();
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return list<GoogleshoppingxmlFeed>
     */
    private function feeds(mixed $feed): array
    {
        $query = GoogleshoppingxmlFeedQuery::create()->orderById();

        if (null === $feed || '' === $feed) {
            return iterator_to_array($query->find(), false);
        }

        $found = (clone $query)->filterByLabel((string) $feed)->findOne()
            ?? (ctype_digit((string) $feed) ? (clone $query)->filterById((int) $feed)->findOne() : null);

        return null === $found ? [] : [$found];
    }

    /**
     * @param iterable<GoogleshoppingxmlFeed> $feeds
     *
     * @deprecated the legacy generator reads every combination in memory: use the default mode
     */
    private function executeLegacyGeneration(Filesystem $fs, iterable $feeds): void
    {
        /** @var GoogleShoppingXmlService $googleShoppingXmlService */
        $googleShoppingXmlService = $this->getContainer()->get('googleshoppingxml.service');

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
