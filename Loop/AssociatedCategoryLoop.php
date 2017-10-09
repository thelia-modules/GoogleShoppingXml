<?php

namespace GoogleShoppingXml\Loop;

use GoogleShoppingXml\Model\Map\GoogleshoppingxmlTaxonomyTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\CategoryTableMap;

class AssociatedCategoryLoop extends BaseI18nLoop implements PropelSearchLoopInterface
{
    public function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('category_id'),
            Argument::createAnyTypeArgument('lang_id', 1)
        );
    }

    public function buildModelCriteria()
    {
        $query = CategoryQuery::create();

        if ($this->getCategoryId()) {
            $query->filterById($this->getCategoryId());
        }

        $this->configureI18nProcessing($query, array('TITLE'));

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
            ->addJoinCondition('taxonomy_join', 'taxonomy.lang_id = '. $this->getLangId())
            ->withColumn('taxonomy.google_category', 'google_category')
            ->addAscendingOrderByColumn('i18n_TITLE');

        return $query;
    }


    public function parseResults(LoopResult $loopResult)
    {
        /** @var Category $data */
        foreach ($loopResult->getResultDataCollection() as $data) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow->set("THELIA_CATEGORY_ID", $data->getId());
            $loopResultRow->set("THELIA_CATEGORY_TITLE", $data->getVirtualColumn('i18n_TITLE'));
            $loopResultRow->set("GOOGLE_CATEGORY", $data->getVirtualColumn('google_category'));

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
