<?php

namespace GoogleShoppingXml\Service\Provider;

use Propel\Runtime\Propel;

class LiaSQLQueryService
{
    /**
     * @return \PDOStatement
     */
    public function getPses(int $currencyId)
    {
        // TODO: When per-store pricing is implemented, replace the JOIN on product_price
        // with a JOIN on a future dealer_product_price table (dealer_id + pse_id -> price).
        $sql = '
            SELECT
                pse.id AS "id",
                dsc.lcv_code AS "store_code",
                ds.stock AS "quantity",
                pse.promo AS "promo",
                pp.price AS "price",
                pp.promo_price AS "promo_price",
                p.tax_rule_id AS "TAX_RULE_ID"
            FROM product_sale_elements AS pse
            JOIN product AS p ON pse.product_id = p.id
            JOIN dealer_stock AS ds ON ds.pse_id = pse.id
            JOIN dealer_stock_config AS dsc ON dsc.dealer_id = ds.dealer_id
            JOIN product_price AS pp ON pp.product_sale_elements_id = pse.id AND pp.currency_id = :currency_id
            WHERE p.visible = 1
              AND dsc.lcv_code IS NOT NULL
              AND dsc.lcv_code != ""
        ';

        $con = Propel::getConnection();

        /** @var \PDOStatement $stmt */
        $stmt = $con->prepare($sql);
        $stmt->bindValue(':currency_id', $currencyId, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }
}
