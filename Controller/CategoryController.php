<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'GoogleShoppingXml', AccessManager::VIEW)) {
            return $response;
        }

        $redirectParameters = [
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced',
        ];

        if (!$id_category = $request->get('additional_category_id')) {
            return $this->generateRedirectFromRoute('admin.module.configure', [], $redirectParameters);
        }

        GoogleshoppingxmlIgnoreCategoryQuery::create()->findOneByCategoryId($id_category)->setIsExportable(1)->save();

        return $this->generateRedirectFromRoute('admin.module.configure', [], $redirectParameters);
    }

    public function addCategory(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'GoogleShoppingXml', AccessManager::VIEW)) {
            return $response;
        }

        $redirectParameters = [
            'module_code' => 'GoogleShoppingXml',
            'current_tab' => 'advanced',
        ];

        if (!$id_category = $request->get('selectedId')) {
            return $this->generateRedirectFromRoute('admin.module.configure', [], $redirectParameters);
        }
        GoogleshoppingxmlIgnoreCategoryQuery::create()->findOneByCategoryId($id_category)->setIsExportable(0)->save();

        return $this->generateRedirectFromRoute('admin.module.configure', [], $redirectParameters);
    }
}
