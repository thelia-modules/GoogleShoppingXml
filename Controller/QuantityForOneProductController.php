<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\GoogleShoppingXml;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Tests\Extension\Core\Type\NumberTypeTest;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

class QuantityForOneProductController extends BaseAdminController
{
    public function saveQuantityForOneProduct(){

        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'GoogleShoppingXml', AccessManager::VIEW)) {
            return $response;
        }

        $request = $this->getRequest();

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