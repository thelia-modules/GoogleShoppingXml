<?php

namespace GoogleShoppingXml\Loop;

use GoogleShoppingXml\Model\GoogleshoppingxmlTaxonomy;
use GoogleShoppingXml\Model\GoogleshoppingxmlTaxonomyQuery;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\CategoryQuery;

/**
 * This loop allows to get the Google category related to a Thelia category.
 * If no Google category is found, the loop searches in the Thelia category's parents.
 * @package GoogleShoppingXml\Loop
 */
class GetGoogleCategoryOrParentLoop extends BaseLoop implements ArraySearchLoopInterface
{
    public function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('category_id'),
            Argument::createAnyTypeArgument('lang_id', 1)
        );
    }

    public function buildArray()
    {
        $category_id = $this->getCategoryId();
        $lang_id = $this->getLangId();
        $google_category = null;

        $google_category = GoogleshoppingxmlTaxonomyQuery::create()
            ->filterByTheliaCategoryId($category_id)
            ->filterByLangId($lang_id)
            ->findOne();

        while ($google_category == null) {
            if (($category = CategoryQuery::create()->findOneById($category_id)) == null
                || ($parent_category_id = $category->getParent()) == 0) {
                return [];
            }
            $category_id = $parent_category_id;

            $google_category = GoogleshoppingxmlTaxonomyQuery::create()
                ->filterByTheliaCategoryId($category_id)
                ->filterByLangId($lang_id)
                ->findOne();
        }

        return array($google_category);
    }


    public function parseResults(LoopResult $loopResult)
    {
        /** @var GoogleshoppingxmlTaxonomy $data */
        foreach ($loopResult->getResultDataCollection() as $data) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("GOOGLE_CATEGORY", $data->getGoogleCategory());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
