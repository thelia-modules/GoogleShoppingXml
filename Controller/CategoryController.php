<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Model\GoogleshoppingxmlIgnoreCategoryQuery;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

class CategoryController extends BaseAdminController
{
    public function deleteCategory(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'GoogleShoppingXml', AccessManager::VIEW)) {
            return $response;
        }

        $redirectParameters = array(
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced'
        );

        if (!$id_category = $request->get('additional_category_id')){
            return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
        }

        GoogleshoppingxmlIgnoreCategoryQuery::create()
            ->filterByCategoryId($id_category)
            ->delete();

        return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
    }

    public function addCategory(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'GoogleShoppingXml', AccessManager::VIEW)) {
            return $response;
        }

        $redirectParameters = array(
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced'
        );

        if (!$id_category = $request->get('selectedId')){
            return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
        }

        GoogleshoppingxmlIgnoreCategoryQuery::create()
            ->filterByCategoryId($id_category)
            ->findOneOrCreate()
            ->setIsExportable(0)
            ->save();

        return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
    }

}