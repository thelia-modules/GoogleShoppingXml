<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Service;

use Propel\Runtime\Connection\StatementInterface;
use Propel\Runtime\Propel;

/**
 * The combinations kept out of the Google Shopping feeds. A combination with no row, or with a row at 0, is in the
 * feed. Every method runs a fixed number of queries whatever the number of combinations.
 */
final class ExclusionRepository
{
    private const CHUNK_SIZE = 500;

    /**
     * The combinations of a product with their reference, the attribute values that tell them
     * apart (in the given language) and whether they are excluded.
     *
     * @return list<array{id: int, reference: string, label: string, excluded: bool}>
     */
    public function combinationsOfProduct(int $productId, string $locale): array
    {
        $combinations = $this->select(
            'SELECT pse.id, pse.ref, COALESCE(excluded.is_excluded, 0) AS is_excluded'
            .' FROM product_sale_elements AS pse'
            .' LEFT JOIN googleshoppingxml_product_excluded AS excluded ON excluded.pse_id = pse.id'
            .' WHERE pse.product_id = ? ORDER BY pse.id',
            [$productId],
        );
        if ([] === $combinations) {
            return [];
        }

        $combinationIds = array_map(static fn (array $row): int => (int) $row['id'], $combinations);
        $titles = [];
        $values = $this->select(
            'SELECT combination.product_sale_elements_id AS combination_id, translation.title'
            .' FROM attribute_combination AS combination'
            .' INNER JOIN attribute_av_i18n AS translation ON translation.id = combination.attribute_av_id AND translation.locale = ?'
            .' WHERE combination.product_sale_elements_id IN ('.$this->placeholders($combinationIds).')'
            .' ORDER BY combination.position, combination.attribute_id',
            [$locale, ...$combinationIds],
        );
        foreach ($values as $value) {
            $titles[(int) $value['combination_id']][] = (string) $value['title'];
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'reference' => (string) $row['ref'],
            'label' => implode(' / ', $titles[(int) $row['id']] ?? []),
            'excluded' => 1 === (int) $row['is_excluded'],
        ], $combinations);
    }

    /**
     * @return list<int>
     */
    public function combinationIdsOfProduct(int $productId): array
    {
        return array_map('intval', array_column(
            $this->select('SELECT id FROM product_sale_elements WHERE product_id = ? ORDER BY id', [$productId]),
            'id',
        ));
    }

    /**
     * @return list<int>
     */
    public function excludedCombinationIdsOfProduct(int $productId): array
    {
        return array_map('intval', array_column(
            $this->select(
                'SELECT pse.id FROM product_sale_elements AS pse'
                .' INNER JOIN googleshoppingxml_product_excluded AS excluded ON excluded.pse_id = pse.id AND excluded.is_excluded = 1'
                .' WHERE pse.product_id = ? ORDER BY pse.id',
                [$productId],
            ),
            'id',
        ));
    }

    /**
     * Writes the exclusion of many combinations at once. Combinations that do not exist are
     * left aside and returned, so the caller can report them.
     *
     * @param array<int, bool> $excludedByCombinationId
     *
     * @return list<int> the ids of the combinations that do not exist
     */
    public function save(array $excludedByCombinationId): array
    {
        $unknown = [];

        foreach (array_chunk($excludedByCombinationId, self::CHUNK_SIZE, true) as $chunk) {
            $ids = array_keys($chunk);
            $existing = array_map('intval', array_column(
                $this->select('SELECT id FROM product_sale_elements WHERE id IN ('.$this->placeholders($ids).')', $ids),
                'id',
            ));

            $parameters = [];
            foreach ($chunk as $combinationId => $excluded) {
                if (!\in_array($combinationId, $existing, true)) {
                    $unknown[] = $combinationId;
                    continue;
                }
                array_push($parameters, $combinationId, $excluded ? 1 : 0);
            }
            if ([] === $parameters) {
                continue;
            }

            $this->execute(
                'INSERT INTO googleshoppingxml_product_excluded (pse_id, is_excluded) VALUES '
                .implode(',', array_fill(0, intdiv(\count($parameters), 2), '(?, ?)'))
                .' ON DUPLICATE KEY UPDATE is_excluded = VALUES(is_excluded)',
                $parameters,
            );
        }

        return $unknown;
    }

    /**
     * A combination of the cloned product takes the exclusion of the combination of the original
     * product that has the same attribute values.
     */
    public function copyToClonedProduct(int $originalProductId, int $clonedProductId): void
    {
        $signatures = 'SELECT pse.id AS pse_id, COALESCE(GROUP_CONCAT(combination.attribute_av_id ORDER BY combination.attribute_av_id), \'\') AS signature'
            .' FROM product_sale_elements AS pse'
            .' LEFT JOIN attribute_combination AS combination ON combination.product_sale_elements_id = pse.id'
            .' WHERE pse.product_id = ? GROUP BY pse.id';

        $this->execute(
            'INSERT INTO googleshoppingxml_product_excluded (pse_id, is_excluded)'
            .' SELECT cloned.pse_id, original_excluded.is_excluded'
            .' FROM ('.$signatures.') AS cloned'
            .' INNER JOIN (SELECT MIN(pse_id) AS pse_id, signature FROM ('.$signatures.') AS signed GROUP BY signature) AS original ON original.signature = cloned.signature'
            .' INNER JOIN googleshoppingxml_product_excluded AS original_excluded ON original_excluded.pse_id = original.pse_id'
            .' ON DUPLICATE KEY UPDATE is_excluded = VALUES(is_excluded)',
            [$clonedProductId, $originalProductId],
        );
    }

    /**
     * @param list<int|string> $values
     */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, \count($values), '?'));
    }

    /**
     * @param list<int|string> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function select(string $sql, array $parameters): array
    {
        return $this->run($sql, $parameters)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param list<int|string> $parameters
     */
    private function execute(string $sql, array $parameters): void
    {
        $this->run($sql, $parameters);
    }

    /**
     * @param list<int|string> $parameters
     */
    private function run(string $sql, array $parameters): StatementInterface
    {
        $statement = Propel::getConnection()->prepare($sql);
        foreach (array_values($parameters) as $index => $parameter) {
            $statement->bindValue($index + 1, $parameter, \is_int($parameter) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $statement->execute();

        return $statement;
    }
}
