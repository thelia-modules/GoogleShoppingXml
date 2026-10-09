<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Feed;

use Propel\Runtime\Propel;

/**
 * The categories of the shop for a feed, read once: the path of each one ("Helmets > Open face", titles
 * in the language of the feed, another language when it has none) and its Google category, the one
 * associated with it or, failing that, with its nearest parent.
 */
final class Categories
{
    /** @var array<int, ?string> */
    private array $paths = [];

    /** @var array<int, string> */
    private array $googleCategories = [];

    public function __construct(string $locale, int $langId)
    {
        $statement = Propel::getConnection()->prepare(
            'SELECT category.id, category.parent, COALESCE(NULLIF(translation.title, \'\'),'
            .' (SELECT other.title FROM category_i18n AS other WHERE other.id = category.id AND other.title <> \'\' ORDER BY other.locale LIMIT 1)) AS title'
            .' FROM category LEFT JOIN category_i18n AS translation ON translation.id = category.id AND translation.locale = ?',
        );
        $statement->bindValue(1, $locale);
        $statement->execute();

        $categories = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $categories[(int) $row['id']] = ['parent' => (int) $row['parent'], 'title' => null === $row['title'] ? null : (string) $row['title']];
        }

        $statement = Propel::getConnection()->prepare('SELECT thelia_category_id, google_category FROM googleshoppingxml_taxonomy WHERE lang_id = ?');
        $statement->bindValue(1, $langId, \PDO::PARAM_INT);
        $statement->execute();
        $associated = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $associated[(int) $row['thelia_category_id']] = (string) $row['google_category'];
        }

        foreach (array_keys($categories) as $id) {
            $this->paths[$id] = $this->path($categories, $id);

            $current = $id;
            $seen = [];
            while (0 !== $current && isset($categories[$current]) && !isset($associated[$current]) && !isset($seen[$current])) {
                $seen[$current] = true;
                $current = $categories[$current]['parent'];
            }
            if (isset($associated[$current])) {
                $this->googleCategories[$id] = $associated[$current];
            }
        }
    }

    public function pathOf(?int $categoryId): ?string
    {
        return null === $categoryId ? null : $this->paths[$categoryId] ?? null;
    }

    public function googleCategoryOf(?int $categoryId): ?string
    {
        return null === $categoryId ? null : $this->googleCategories[$categoryId] ?? null;
    }

    /**
     * @param array<int, array{parent: int, title: ?string}> $categories
     */
    private function path(array $categories, int $id): ?string
    {
        $titles = [];
        $seen = [];
        while (0 !== $id && isset($categories[$id]) && !isset($seen[$id])) {
            $seen[$id] = true;
            if (null === $categories[$id]['title']) {
                return null;
            }
            array_unshift($titles, $categories[$id]['title']);
            $id = $categories[$id]['parent'];
        }

        return 0 === $id ? implode(' > ', $titles) : null;
    }
}
