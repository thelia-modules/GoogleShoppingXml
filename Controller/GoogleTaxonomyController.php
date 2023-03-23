<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Form\GoogleTaxonomyForm;
use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlTaxonomyQuery;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

class GoogleTaxonomyController extends BaseAdminController
{
    protected function getGoogleTaxonomiesFileURL($lang)
    {
        return "https://www.google.com/basepages/producttype/taxonomy-with-ids.".str_replace("_", "-", $lang->getLocale()).".txt";
    }

    protected function fetchGoogleTaxonomy($langId = null, $htmlEncode = false)
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
            $lineParts = explode(' - ', $row);
            $categoryId = array_shift($lineParts);
            $categoryFullname = implode(' - ', $lineParts);

            if ($htmlEncode) {
                $categoryFullname = htmlspecialchars($categoryFullname);
            }

            $categories[$categoryId] =  $categoryFullname;
        }

        return $categories;
    }

    protected function fetchGoogleCatNameFromId($google_cat_id, $langId)
    {
        $googleCatsArray = $this->fetchGoogleTaxonomy($langId, false);
        return $googleCatsArray[$google_cat_id];
    }

    public function getJsonGoogleTaxonomyAction($langId = null)
    {
        $googleCatsArray = $this->fetchGoogleTaxonomy($langId, true);

        return new JsonResponse(['cats' => $googleCatsArray]);
    }


    public function associateTaxonomyAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::CREATE)) {
            return $response;
        }

        $form = $this->createForm(GoogleTaxonomyForm::class);

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
                Translator::getInstance()->trans("GoogleShoppingXml configuration", [], GoogleShoppingXml::DOMAIN_NAME),
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

    public function deleteTaxonomyAction(Request $request)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('GoogleShoppingXml'), AccessManager::DELETE)) {
            return $response;
        }

        $categoryId = $request->request->get('category_id');

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
