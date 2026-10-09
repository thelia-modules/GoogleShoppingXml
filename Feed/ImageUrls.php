<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Feed;

use GoogleShoppingXml\Exception\FeedGenerationException;
use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Model\ConfigQuery;

/**
 * The address of a product image for a filter set of the image library, without host, so that each
 * feed puts the domain of its language in front. An image whose file is missing has no address:
 * Google would refuse the item for a broken image.
 */
final class ImageUrls
{
    /** @var array<string, ?string> */
    private array $paths = [];

    public function __construct(
        private readonly CacheManager $cacheManager,
        #[Autowire(service: 'liip_imagine.filter.configuration')]
        private readonly FilterConfiguration $filterConfiguration,
    ) {
    }

    /**
     * @throws FeedGenerationException when the filter set is not configured
     */
    public function assertFilterSetExists(string $filterSet): void
    {
        try {
            $this->filterConfiguration->get($filterSet);
        } catch (NonExistingFilterException) {
            throw FeedGenerationException::unknownImageFilter($filterSet);
        }
    }

    public function pathOf(string $file, string $filterSet): ?string
    {
        $key = $file.'|'.$filterSet;
        if (\array_key_exists($key, $this->paths)) {
            return $this->paths[$key];
        }

        if ('' === $file || !is_file($this->sourceDirectory().'/'.$file)) {
            return $this->paths[$key] = null;
        }

        $parts = parse_url($this->cacheManager->getBrowserPath('/product/'.$file, $filterSet));
        if (!\is_array($parts) || !isset($parts['path'])) {
            return $this->paths[$key] = null;
        }

        return $this->paths[$key] = $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function sourceDirectory(): string
    {
        $library = ConfigQuery::read('images_library_path');

        $directory = null === $library || '' === $library
            ? THELIA_LOCAL_DIR.'media'.\DIRECTORY_SEPARATOR.'images'
            : THELIA_ROOT.$library;

        return rtrim($directory, '/\\').\DIRECTORY_SEPARATOR.'product';
    }
}
