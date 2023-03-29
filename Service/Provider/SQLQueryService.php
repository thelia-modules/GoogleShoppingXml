<?php

namespace GoogleShoppingXml\Service\Provider;

use Propel\Runtime\Propel;
use Thelia\Tools\URL;

class SQLQueryService
{
    /**
     * @param string $locale
     */
    public function getPses(string $locale)
    {
        $baseUrl = URL::getInstance()->absoluteUrl('/');

        $sql = '
            WITH RECURSIVE category_path (id, title, path) AS
            (
              SELECT cat.id catid, cin.title, cin.title AS path
                FROM category cat
                JOIN category_i18n cin ON cin.id=cat.id
                LEFT JOIN googleshoppingxml_ignore_category gc ON gc.category_id=cat.id                
                WHERE parent=0 AND gc.id IS NULL
              UNION ALL
              SELECT c.id, ci.title, CONCAT(cp.path, " > ", ci.title)
                FROM category_path AS cp 
                JOIN category AS c
                JOIN category_i18n ci ON cp.id = c.parent AND ci.id=c.id AND ci.locale=:p0
                LEFT JOIN googleshoppingxml_ignore_category gc ON gc.category_id=c.id 
                WHERE gc.id IS NULL
                
            ), attribute_title AS (
                SELECT ac.product_sale_elements_id AS id, GROUP_CONCAT(DISTINCT avi.title ORDER BY a.id SEPARATOR " - ") AS title	
                from attribute_combination AS ac
                JOIN attribute a ON a.id = ac.attribute_id	
                JOIN attribute_av av ON ac.attribute_av_id = av.id
                JOIN attribute_av_i18n avi ON av.id = avi.id	
                WHERE avi.locale=:p1
                GROUP BY ac.product_sale_elements_id                 
            )
            
            SELECT
                pse.id AS "id", 
                CONCAT(pi.title," - ", attrib.title) AS "title",
                pi.description AS "description",                
                IF(pse.quantity>0, "in stock", "out of stock") AS "availability",
                CONCAT(@BASEURL := "' . $baseUrl . '",rurl.url) AS "link",
                productimg.id AS "image_link",
                IF(pse.promo=1, pp.promo_price, pp.price) AS "price",
                bi.title AS "brand",
                IF(pse.ean_code!="", "yes", "no") AS "identifier_exists",
                pse.ean_code AS "gtin",
                p.ref AS "item_group_id",
                gt.google_category AS "google_product_category",
                cp.path AS "product_type",
                "new" AS "condition",
                p.tax_rule_id AS "TAX_RULE_ID"
            
            FROM `product_sale_elements`AS pse
            
            JOIN product AS p ON pse.product_id = p.id
            JOIN product_i18n AS pi ON p.id = pi.id
            JOIN attribute_title AS attrib ON attrib.id = pse.id
            JOIN rewriting_url AS rurl ON rurl.view = "product" AND rurl.view_id = p.id
            JOIN product_price AS pp ON pp.product_sale_elements_id = pse.id
            JOIN brand AS b ON b.id = p.brand_id
            JOIN brand_i18n AS bi ON b.id = bi.id
            JOIN product_category AS pc ON pc.product_id=p.id AND pc.default_category=1
            JOIN product_image AS productimg ON productimg.product_id = p.id AND productimg.position=1
            JOIN category AS c ON c.id=pc.category_id
            LEFT JOIN googleshoppingxml_ignore_category gc ON gc.category_id=c.id
            JOIN googleshoppingxml_taxonomy AS gt ON gt.thelia_category_id = c.id
            JOIN category_path cp ON cp.id = pc.category_id
            JOIN (
            	SELECT p.id AS "product_id", pse.quantity AS "stock" FROM product p JOIN `product_sale_elements` pse ON pse.`product_id` = p.id WHERE pse.quantity>0 GROUP BY p.id HAVING COUNT(pse.id) > 2
                UNION
                SELECT p.id AS "product_id", pse.quantity AS "stock" FROM product p JOIN `product_sale_elements` pse ON pse.`product_id` = p.id WHERE 1 GROUP BY p.id HAVING COUNT(pse.id) = 1 AND pse.quantity > 0
                ) AS stock ON stock.`product_id`=p.id    
                    
            WHERE 
            pi.locale=:p2 
            AND rurl.view_locale=:p3 
            AND bi.locale=:p4 
            AND p.visible=1
            AND pi.title!=""
            AND pi.description!=""
            AND gc.id IS NULL
            GROUP BY pse.id';

        $con = Propel::getConnection();

        /** @var PDOStatement $stmt */
        $stmt = $con->prepare($sql);

        $stmt->bindValue(':p0', $locale);
        $stmt->bindValue(':p1', $locale);
        $stmt->bindValue(':p2', $locale);
        $stmt->bindValue(':p3', $locale);
        $stmt->bindValue(':p4', $locale);

        $stmt->execute();

        return $stmt;
    }
}