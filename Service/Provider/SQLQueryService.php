<?php

namespace GoogleShoppingXml\Service\Provider;

use Propel\Runtime\Propel;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;

class SQLQueryService
{
    /**
     * @param string $locale
     */
    public function getPsesWithSqlOptimisation(string $locale)
    {
        $baseUrl = URL::getInstance()->absoluteUrl('/');

        $sql = '
            WITH RECURSIVE category_path (id, title, path) AS
            (
              SELECT cat.id catid, cin.title, cin.title AS path
                FROM category cat
                JOIN category_i18n cin ON cin.id=cat.id 
                WHERE parent=0
              UNION ALL
              SELECT c.id, ci.title, CONCAT(cp.path, " > ", ci.title)
                FROM category_path AS cp 
                JOIN category AS c 
                JOIN category_i18n ci ON cp.id = c.parent AND ci.id=c.id AND ci.locale=:p0
                
            ), attribute_title AS (
                SELECT ac.product_sale_elements_id AS id, GROUP_CONCAT(DISTINCT avi.title ORDER BY a.id SEPARATOR " - ") AS title	
                from attribute_combination AS ac
                LEFT JOIN attribute a ON a.id = ac.attribute_id	
                LEFT JOIN attribute_av av ON ac.attribute_av_id = av.id
                LEFT JOIN attribute_av_i18n avi ON av.id = avi.id	
                WHERE avi.locale=:p1 
                GROUP BY ac.product_sale_elements_id 
                
            ), product_images_query AS (
                SELECT pimg.product_id as pid, pimg.file AS product_image_file, pimg.id as product_image_id
                FROM product_image AS pimg
                GROUP BY pimg.product_id 
                ORDER BY pimg.position
                
            ), pse_images_query AS (
                SELECT pseimg.`product_sale_elements_id` as pseid, pimg.file as pse_image_file, pimg.id as pse_image_id
                FROM product_sale_elements_product_image AS pseimg
                JOIN product_image AS pimg ON pimg.id=pseimg.product_image_id
                WHERE pimg.`product_id`=1
                GROUP BY pseimg.`product_sale_elements_id`
            )
            
            SELECT
                pse.id AS "id", 
                pi.title AS "product_title",
                attrib.title AS "attrib_title",
                pi.description AS "description",                
                IF(pse.quantity>0, "in stock", "out of stock") AS "availability",
                CONCAT(@BASEURL := "' . $baseUrl . '",rurl.url) AS "link",
                IF(pseimgs.pse_image_id != "", pseimgs.pse_image_id, pimgs.product_image_id) AS "image_link",
                IF(pse.promo=1, pp.promo_price, pp.price) AS "price",
                bi.title AS "brand",
                IF(pse.ean_code!="", "yes", "no") AS "identifier_exists",
                pse.ean_code AS "gtin",
                p.ref AS "item_group_id",
                pse.weight AS "shipping_weight",
                gt.google_category AS "google_product_category",
                cp.path AS "product_type",
                "new" AS "condition",
                p.tax_rule_id AS "TAX_RULE_ID"
            
            FROM `product_sale_elements`AS pse
            
            LEFT JOIN product AS p ON pse.product_id = p.id
            LEFT JOIN product_i18n AS pi ON p.id = pi.id AND pi.locale=:p2 
            LEFT JOIN attribute_title AS attrib ON attrib.id = pse.id
            LEFT JOIN rewriting_url AS rurl ON rurl.view = "product" AND rurl.view_id = p.id AND rurl.view_locale=:p3 
            LEFT JOIN product_price AS pp ON pp.product_sale_elements_id = pse.id
            LEFT JOIN brand AS b ON b.id = p.brand_id
            LEFT JOIN brand_i18n AS bi ON b.id = bi.id AND bi.locale=:p4 
            LEFT JOIN product_category AS pc ON pc.product_id=p.id AND pc.default_category=1
            LEFT JOIN product_images_query AS pimgs ON pimgs.pid=p.id
            LEFT JOIN pse_images_query AS pseimgs ON pseimgs.pseid=pse.id
            LEFT JOIN category AS c ON c.id=pc.category_id
            LEFT JOIN googleshoppingxml_taxonomy AS gt ON gt.thelia_category_id = c.id
            LEFT JOIN category_path cp ON cp.id = pc.category_id
            
            WHERE 
            
            p.visible=1
            AND pi.title!=""
            AND pi.description!=""
            
            GROUP BY pse.id
        ';

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

    public function getPses(string $locale)
    {
        $baseUrl = URL::getInstance()->absoluteUrl('/');

        $sql = '
            SELECT
                pse.id AS "id", 
                pi.title AS "product_title",
                GROUP_CONCAT(DISTINCT avi.title ORDER BY a.id SEPARATOR " - ") AS "attrib_title",
                pi.description AS "description",                
                IF(pse.quantity>0, "in stock", "out of stock") AS "availability",
                CONCAT(@BASEURL := "' . $baseUrl . '",rurl.url) AS "link",
                IF(pse.promo=1, pp.promo_price, pp.price) AS "price",
                bi.title AS "brand",
                IF(pse.ean_code!="", "yes", "no") AS "identifier_exists",
                pse.ean_code AS "gtin",
                p.ref AS "item_group_id",
                pse.weight AS "shipping_weight",
                gt.google_category AS "google_product_category",
                "new" AS "condition",
                p.tax_rule_id AS "TAX_RULE_ID",
                c.id AS "default_category_id"
            
            FROM `product_sale_elements`AS pse
            
            LEFT JOIN product AS p ON pse.product_id = p.id
            LEFT JOIN product_i18n AS pi ON p.id = pi.id AND pi.locale=:p2
            LEFT JOIN attribute_combination AS ac ON ac.product_sale_elements_id = pse.id
            LEFT JOIN attribute AS a ON a.id = ac.attribute_id	
            LEFT JOIN attribute_av AS av ON ac.attribute_av_id = av.id
            LEFT JOIN attribute_av_i18n AS avi ON av.id = avi.id 
            LEFT JOIN rewriting_url AS rurl ON rurl.view = "product" AND rurl.view_id = p.id AND rurl.view_locale=:p3 
            LEFT JOIN product_price AS pp ON pp.product_sale_elements_id = pse.id
            LEFT JOIN brand AS b ON b.id = p.brand_id
            LEFT JOIN brand_i18n AS bi ON b.id = bi.id AND bi.locale=:p4 
            LEFT JOIN product_category AS pc ON pc.product_id=p.id AND pc.default_category=1
            LEFT JOIN category AS c ON c.id=pc.category_id
            LEFT JOIN googleshoppingxml_taxonomy AS gt ON gt.thelia_category_id = c.id
            
            WHERE 
            
            p.visible=1
            AND pi.title!=""
            AND pi.description!=""
            
            GROUP BY pse.id';

        $con = Propel::getConnection();

        /** @var PDOStatement $stmt */
        $stmt = $con->prepare($sql);

        //$stmt->bindValue(':p1', $locale);
        $stmt->bindValue(':p2', $locale);
        $stmt->bindValue(':p3', $locale);
        $stmt->bindValue(':p4', $locale);

        $stmt->execute();

        return $stmt;

    }
}