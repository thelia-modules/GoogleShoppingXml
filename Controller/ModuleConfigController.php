<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlIgnoreCategoryQuery;
use Propel\Runtime\Propel;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Model\ModuleQuery;
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
        $brandRule = GoogleShoppingXml::getConfigValue("brand_rule", 0);

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

        $dealerModuleId = ($v = GoogleShoppingXml::getConfigValue('dealer_module_id', null)) ? (int) $v : null;
        $dealerModule = $dealerModuleId ? ModuleQuery::create()->filterByActivate(1)->findPk($dealerModuleId) : null;

        $modules = ModuleQuery::create()->filterByActivate(1)->find();

        return $this->render(
            "xml-module-configuration",
            [
                'field_association_array' => $fieldAssociationArray,
                'pse_count' => $this->getNumberOfPse(),
                'ean_rule' => $ean_rule,
                'brand_rule' => $brandRule,
                'ignoreCategoryList' => $ignoreCategoryList,
                'quantity_for_one_product'=>$quantityForOneProduct,
                'dealer_module_active' => (bool) $dealerModule,
                'dealer_module_id' => $dealerModuleId,
                'modules' => $modules
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
