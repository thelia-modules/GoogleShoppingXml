<?php

namespace GoogleShoppingXml\Model\Tools;

use GoogleShoppingXml\Events\LogEvents;
use GoogleShoppingXml\Events\PropelEvent;
use GoogleShoppingXml\Model\GoogleshoppingxmlLogQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Tools\ModelEventDispatcherTrait as TheliaModelEventDispatcherTrait;

trait ModelEventDispatcherTrait
{
    protected function getTableName()
    {
        $tableMapClass = self::TABLE_MAP;
        return $tableMapClass::TABLE_NAME;
    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        $dateSuppression = date("Y-m-d h:i:s",mktime(0, 0, 0, date("m")-1, date("d"),   date("Y")));

        GoogleshoppingxmlLogQuery::create()
            ->filterByCreatedAt($dateSuppression, Criteria::LESS_EQUAL)
            ->delete();

        return true;
    }
}