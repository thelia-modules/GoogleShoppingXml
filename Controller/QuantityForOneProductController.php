<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\GoogleShoppingXml;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

class QuantityForOneProductController extends BaseAdminController
{
    public function saveQuantityForOneProduct(Request $request){

        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'GoogleShoppingXml', AccessManager::VIEW)) {
            return $response;
        }

        $redirectParameters = array(
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced'
        );

        $quantityForOneProduct = $request->get('quantityForOneProduct');

        if ($quantityForOneProduct === null){
            return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
        }

        if ($quantityForOneProduct < 0){
            return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
        }

        GoogleShoppingXml::setConfigValue("quantityForOneProduct",$quantityForOneProduct);

        return $this->generateRedirectFromRoute("admin.module.configure", array(), $redirectParameters);
    }
}