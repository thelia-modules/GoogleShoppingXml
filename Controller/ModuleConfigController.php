<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlIgnoreCategoryQuery;
use Propel\Runtime\Propel;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Map\CategoryI18nTableMap;

class ModuleConfigController extends BaseAdminController
{
    public function viewConfigAction($params = array())
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'GoogleShoppingXml', AccessManager::VIEW)) {
            return $response;
        }

        $fieldAssociationArray = GoogleshoppingxmlGoogleFieldAssociationQuery::create()->find()->toArray();

        $ean_rule = GoogleShoppingXml::getConfigValue("ean_rule", FeedXmlController::DEFAULT_EAN_RULE);

        $locale = $this->getRequest()->getSession()->getLang()->getLocale();

        $ignoreCategoryList = GoogleshoppingxmlIgnoreCategoryQuery::create()
            ->addAsColumn('category_title', CategoryI18nTableMap::COL_TITLE)
            ->addAsColumn('category_id', CategoryI18nTableMap::COL_ID)
            ->useCategoryQuery()
                ->useCategoryI18nQuery()
                    ->filterByLocale($locale)
                ->endUse()
            ->endUse()
            ->find()
            ->toArray();

        $quantityForOneProduct = GoogleShoppingXml::getConfigValue("quantityForOneProduct",null);


        return $this->render(
            "xml-module-configuration",
            [
                'field_association_array' => $fieldAssociationArray,
                'pse_count' => $this->getNumberOfPse(),
                'ean_rule' => $ean_rule,
                'ignoreCategoryList' => $ignoreCategoryList,
                'quantity_for_one_product'=>$quantityForOneProduct
            ]
        );
    }

    protected function getNumberOfPse()
    {
        $sql = 'SELECT COUNT(*) AS nb FROM product_sale_elements';
        $stmt = Propel::getConnection()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $rows[0]['nb'];
    }
}
