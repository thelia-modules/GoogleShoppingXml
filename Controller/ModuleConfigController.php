<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlIgnoreCategory;
use GoogleShoppingXml\Model\GoogleshoppingxmlIgnoreCategoryQuery;
use Propel\Runtime\Propel;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CategoryQuery;

class ModuleConfigController extends BaseAdminController
{
    public function viewConfigAction($params = array())
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'GoogleShoppingXml', AccessManager::VIEW)) {
            return $response;
        }

        $fieldAssociationArray = GoogleshoppingxmlGoogleFieldAssociationQuery::create()->find()->toArray();

        $ean_rule = GoogleShoppingXml::getConfigValue("ean_rule", FeedXmlController::DEFAULT_EAN_RULE);

        $ignoreCategoryList = GoogleshoppingxmlIgnoreCategoryQuery::create()->find();

        if(!$ignoreCategoryList->getData())
        {
            if($categoryList = CategoryQuery::create()->find()->getData()){
                foreach ($categoryList as $category){
                    $ignoreCategory=new GoogleshoppingxmlIgnoreCategory();
                    $ignoreCategory->setCategoryId($category->getId());
                    $ignoreCategory->save();
                }
            }
        }
        $ignoreCategoryList = GoogleshoppingxmlIgnoreCategoryQuery::create()->find()->toArray();


        return $this->render(
            "module-configuration",
            [
                'field_association_array' => $fieldAssociationArray,
                'pse_count' => $this->getNumberOfPse(),
                'ean_rule' => $ean_rule,
                'ignore_category_array' => $ignoreCategoryList
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
