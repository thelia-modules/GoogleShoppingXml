<?php

namespace GoogleShoppingXml\Controller;

use GoogleShoppingXml\Form\CompatibilitySqlForm;
use GoogleShoppingXml\Form\FeedManagementForm;
use GoogleShoppingXml\Form\GoogleTaxonomyForm;
use GoogleShoppingXml\GoogleShoppingXml;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Model\GoogleshoppingxmlGoogleFieldAssociationQuery;
use GoogleShoppingXml\Model\Map\GoogleshoppingxmlTaxonomyTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AttributeQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\FeatureQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\CategoryTableMap;

class ModuleConfigController extends BaseAdminController
{
    public function __construct(private readonly \Twig\Environment $twig)
    {
    }

    public function viewConfigAction(): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'GoogleShoppingXml', AccessManager::VIEW)) {
            return $response;
        }

        $langId = (int) $this->getCurrentEditionLang()->getId();
        $locale = $this->getCurrentEditionLang()->getLocale();

        $fieldAssociationArray = GoogleshoppingxmlGoogleFieldAssociationQuery::create()->find()->toArray();
        $eanRule = GoogleShoppingXml::getConfigValue('ean_rule', FeedXmlController::DEFAULT_EAN_RULE);

        $feedManagementForm = $this->createForm(FeedManagementForm::getName());
        $googleTaxonomyForm = $this->createForm(GoogleTaxonomyForm::getName());
        $compatibilitySqlForm = $this->createForm(
            CompatibilitySqlForm::getName(),
            data: ['enable_optimisation' => (bool) GoogleShoppingXml::getConfigValue(GoogleShoppingXml::ENABLE_SQL_8_COMPATIBILITY)]
        );

        return new Response(
            $this->twig->render('@GoogleShoppingXmlModule/backOffice/default-twig/xml-module-configuration.html.twig', [
                'field_association_array' => $fieldAssociationArray,
                'pse_count' => $this->getNumberOfPse(),
                'ean_rule' => $eanRule,
                'current_lang_id' => $langId,
                'feeds' => $this->getFeeds(),
                'langs' => $this->getLangs(),
                'currencies' => $this->getCurrencies(),
                'countries' => $this->getCountries(),
                'category_tree' => $this->getCategoryTree($locale),
                'attributes' => $this->getAttributes($locale),
                'features' => $this->getFeatures($locale),
                'associated_categories' => $this->getAssociatedCategories($langId, $locale),
                'feed_management_form' => $feedManagementForm->getForm()->createView(),
                'google_taxonomy_form' => $googleTaxonomyForm->getForm()->createView(),
                'compatibility_sql_form' => $compatibilitySqlForm->getForm()->createView(),
            ])
        );
    }

    protected function getNumberOfPse(): int
    {
        $stmt = Propel::getConnection()->prepare('SELECT COUNT(*) AS nb FROM product_sale_elements');
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return (int) $rows[0]['nb'];
    }

    /** @return array<int, array{id: int, label: string, lang_id: int, currency_id: int, country_id: int}> */
    protected function getFeeds(): array
    {
        $feeds = [];
        foreach (GoogleshoppingxmlFeedQuery::create()->find() as $feed) {
            $feeds[] = [
                'id' => (int) $feed->getId(),
                'label' => (string) $feed->getLabel(),
                'lang_id' => (int) $feed->getLangId(),
                'currency_id' => (int) $feed->getCurrencyId(),
                'country_id' => (int) $feed->getCountryId(),
            ];
        }

        return $feeds;
    }

    /** @return array<int, array{id: int, title: string}> */
    protected function getLangs(): array
    {
        $langs = [];
        foreach (LangQuery::create()->orderByPosition()->find() as $lang) {
            $langs[] = ['id' => (int) $lang->getId(), 'title' => (string) $lang->getTitle()];
        }

        return $langs;
    }

    /** @return array<int, array{id: int, symbol: string}> */
    protected function getCurrencies(): array
    {
        $currencies = [];
        foreach (CurrencyQuery::create()->orderByPosition()->find() as $currency) {
            $currencies[] = ['id' => (int) $currency->getId(), 'symbol' => (string) $currency->getSymbol()];
        }

        return $currencies;
    }

    /** @return array<int, array{id: int, title: string}> */
    protected function getCountries(): array
    {
        $countries = [];
        foreach (CountryQuery::create()->find() as $country) {
            $countries[] = ['id' => (int) $country->getId(), 'title' => (string) $country->getTitle()];
        }

        return $countries;
    }

    /** @return array<int, array{id: int, title: string, level: int}> */
    protected function getCategoryTree(string $locale, int $parent = 0, int $level = 0): array
    {
        $rows = [];
        $categories = CategoryQuery::create()
            ->filterByParent($parent)
            ->orderByPosition()
            ->find();

        foreach ($categories as $category) {
            $category->setLocale($locale);
            $rows[] = [
                'id' => (int) $category->getId(),
                'title' => (string) $category->getTitle(),
                'level' => $level,
            ];
            $rows = array_merge($rows, $this->getCategoryTree($locale, (int) $category->getId(), $level + 1));
        }

        return $rows;
    }

    /** @return array<int, array{id: int, title: string}> */
    protected function getAttributes(string $locale): array
    {
        $attributes = [];
        foreach (AttributeQuery::create()->orderByPosition()->find() as $attribute) {
            $attribute->setLocale($locale);
            $attributes[] = ['id' => (int) $attribute->getId(), 'title' => (string) $attribute->getTitle()];
        }

        return $attributes;
    }

    /** @return array<int, array{id: int, title: string}> */
    protected function getFeatures(string $locale): array
    {
        $features = [];
        foreach (FeatureQuery::create()->orderByPosition()->find() as $feature) {
            $feature->setLocale($locale);
            $features[] = ['id' => (int) $feature->getId(), 'title' => (string) $feature->getTitle()];
        }

        return $features;
    }

    /**
     * Reproduces AssociatedCategoryLoop: Thelia categories joined with their Google taxonomy association.
     *
     * @return array<int, array{id: int, title: string, google_category: ?string}>
     */
    protected function getAssociatedCategories(int $langId, string $locale): array
    {
        $query = CategoryQuery::create();

        $taxonomyJoin = new Join();
        $taxonomyJoin->addExplicitCondition(
            CategoryTableMap::TABLE_NAME,
            'ID',
            null,
            GoogleshoppingxmlTaxonomyTableMap::TABLE_NAME,
            'THELIA_CATEGORY_ID',
            'taxonomy'
        );
        $taxonomyJoin->setJoinType(Criteria::JOIN);

        $query->addJoinObject($taxonomyJoin, 'taxonomy_join')
            ->addJoinCondition('taxonomy_join', 'taxonomy.lang_id = '.$langId)
            ->withColumn('taxonomy.google_category', 'google_category')
            ->addAscendingOrderByColumn(CategoryTableMap::COL_ID);

        $rows = [];
        foreach ($query->find() as $category) {
            $category->setLocale($locale);
            $rows[] = [
                'id' => (int) $category->getId(),
                'title' => (string) $category->getTitle(),
                'google_category' => $category->getVirtualColumn('google_category'),
            ];
        }

        return $rows;
    }
}
