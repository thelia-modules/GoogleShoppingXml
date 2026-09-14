<?php

namespace GoogleShoppingXml\Loop;

use GoogleShoppingXml\Service\ShippingMatrixBuilder;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;

/**
 * Countries an active delivery module actually serves.
 *
 * A feed writes one shipping rate per country, so offering a country no carrier covers would
 * only produce an entry the feed then has to drop.
 */
class ServedCountryLoop extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = false;

    public function getArgDefinitions()
    {
        return new ArgumentCollection();
    }

    public function buildModelCriteria()
    {
        $query = CountryQuery::create()
            ->filterByVisible(1)
            ->filterById(ShippingMatrixBuilder::servedCountryIds(), Criteria::IN);

        $this->configureI18nProcessing($query, ['TITLE']);

        return $query->orderBy('i18n_TITLE');
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var Country $country */
        foreach ($loopResult->getResultDataCollection() as $country) {
            $loopResultRow = new LoopResultRow($country);
            $loopResultRow
                ->set('ID', $country->getId())
                ->set('TITLE', $country->getVirtualColumn('i18n_TITLE'))
                ->set('ISOALPHA2', $country->getIsoalpha2());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
