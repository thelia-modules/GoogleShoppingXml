<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Model\GoogleshoppingxmlIgnoreCategoryQuery;
use Thelia\Controller\Admin\BaseAdminController;

class CategoryController extends BaseAdminController
{
    public function deleteCategory(): \Symfony\Component\HttpFoundation\Response
    {
        $request = $this->getRequest();

        $redirectParameters = array(
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced'
        );

        if (!$id_category = $request->get('additional_category_id')){
            return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
        }

        GoogleshoppingxmlIgnoreCategoryQuery::create()->findOneByCategoryId($id_category)->setIsExportable(0)->save();

        return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);


    }

    public function addCategory(): \Symfony\Component\HttpFoundation\Response
    {
        $redirectParameters = array(
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced'
        );

        $request = $this->getRequest();

        if (!$id_category = $request->get('selectedId')){
            return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
        }
        GoogleshoppingxmlIgnoreCategoryQuery::create()->findOneByCategoryId($id_category)->setIsExportable(1)->save();



        return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
    }

}