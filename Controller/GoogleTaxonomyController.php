<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Form\GoogleTaxonomyForm;
use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlTaxonomyQuery;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

class GoogleTaxonomyController extends BaseAdminController
{
    private function getGoogleTaxonomiesFileURL($lang)
    {
        return "http://www.google.com/basepages/producttype/taxonomy-with-ids.".str_replace("_", "-", $lang->getLocale()).".txt";
    }

    private function fetchGoogleTaxonomy($langId = null, $htmlEncode = false)
    {
        $lang = LangQuery::create()->findOneById($langId);

        if ($lang === null) {
            $lang = Lang::getDefaultLanguage();
        }

        $file = file_get_contents($this->getGoogleTaxonomiesFileURL($lang));
        $rows = explode("\n", $file);
        $categories = [];

        array_shift($rows); // Remove the first line : "# Google_Product_Taxonomy_Version: 2015-02-19"
        array_pop($rows); // Remove the last empty line

        foreach ($rows as $row) {
            $line_parts = explode(' - ', $row);
            $cat_id = array_shift($line_parts);
            $cat_fullname = implode(' - ', $line_parts);

            if ($htmlEncode) {
                $cat_fullname = htmlspecialchars($cat_fullname);
            }

            $categories[$cat_id] =  $cat_fullname;
        }

        return $categories;
    }

    private function fetchGoogleCatNameFromId($google_cat_id, $langId)
    {
        $google_cats_array = $this->fetchGoogleTaxonomy($langId, false);
        return $google_cats_array[$google_cat_id];
    }

    public function getJsonGoogleTaxonomyAction($langId = null)
    {
        $google_cats_array = $this->fetchGoogleTaxonomy($langId, true);

        return new JsonResponse(['cats' => $google_cats_array]);
    }


    public function associateTaxonomyAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::CREATE)) {
            return $response;
        }

        $message = null;

        $form = new GoogleTaxonomyForm($this->getRequest());

        try {
            $formData = $this->validateForm($form)->getData();

            $theliaCategoryId = $formData["thelia_category_id"];
            $googleCategoryId = $formData["google_category_id"];

            $activeLanguages = LangQuery::create()->findByActive(true);

            /* @var $lang Lang */
            foreach ($activeLanguages as $lang) {
                $taxonomy = GoogleshoppingxmlTaxonomyQuery::create()
                    ->filterByTheliaCategoryId($theliaCategoryId)
                    ->filterByLangId($lang->getId())
                    ->findOneOrCreate();

                $googleCategoryName = $this->fetchGoogleCatNameFromId($googleCategoryId, $lang->getId());

                $taxonomy->setGoogleCategory($googleCategoryName)
                    ->save();
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("GoogleShoppingXml configuration", [], GoogleShoppingXml::DOMAIN_NAME),
                $message,
                $form,
                $e
            );
        }

        return $this->generateRedirectFromRoute(
            "admin.module.configure",
            array(),
            array(
                'module_code' => 'GoogleShoppingXml',
                'current_tab' => 'taxonomy'
            )
        );
    }

    public function deleteTaxonomyAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::DELETE)) {
            return $response;
        }

        $categoryId = $this->getRequest()->request->get('category_id');

        GoogleshoppingxmlTaxonomyQuery::create()
            ->filterByTheliaCategoryId($categoryId)
            ->delete();

        return $this->generateRedirectFromRoute(
            "admin.module.configure",
            array(),
            array(
                'module_code' => 'GoogleShoppingXml',
                'current_tab' => 'taxonomy'
            )
        );
    }
}
