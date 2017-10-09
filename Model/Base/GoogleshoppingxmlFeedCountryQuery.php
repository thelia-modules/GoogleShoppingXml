<?php

namespace GoogleShoppingXml\Model\Base;

use \Exception;
use \PDO;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountry as ChildGoogleshoppingxmlFeedCountry;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountryQuery as ChildGoogleshoppingxmlFeedCountryQuery;
use GoogleShoppingXml\Model\Map\GoogleshoppingxmlFeedCountryTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Country;

/**
 * Base class that represents a query for the 'googleshoppingxml_feed_country' table.
 *
 *
 *
 * @method     ChildGoogleshoppingxmlFeedCountryQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildGoogleshoppingxmlFeedCountryQuery orderByFeedId($order = Criteria::ASC) Order by the feed_id column
 * @method     ChildGoogleshoppingxmlFeedCountryQuery orderByCountryId($order = Criteria::ASC) Order by the country_id column
 *
 * @method     ChildGoogleshoppingxmlFeedCountryQuery groupById() Group by the id column
 * @method     ChildGoogleshoppingxmlFeedCountryQuery groupByFeedId() Group by the feed_id column
 * @method     ChildGoogleshoppingxmlFeedCountryQuery groupByCountryId() Group by the country_id column
 *
 * @method     ChildGoogleshoppingxmlFeedCountryQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildGoogleshoppingxmlFeedCountryQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildGoogleshoppingxmlFeedCountryQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildGoogleshoppingxmlFeedCountryQuery leftJoinGoogleshoppingxmlFeed($relationAlias = null) Adds a LEFT JOIN clause to the query using the GoogleshoppingxmlFeed relation
 * @method     ChildGoogleshoppingxmlFeedCountryQuery rightJoinGoogleshoppingxmlFeed($relationAlias = null) Adds a RIGHT JOIN clause to the query using the GoogleshoppingxmlFeed relation
 * @method     ChildGoogleshoppingxmlFeedCountryQuery innerJoinGoogleshoppingxmlFeed($relationAlias = null) Adds a INNER JOIN clause to the query using the GoogleshoppingxmlFeed relation
 *
 * @method     ChildGoogleshoppingxmlFeedCountryQuery leftJoinCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the Country relation
 * @method     ChildGoogleshoppingxmlFeedCountryQuery rightJoinCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Country relation
 * @method     ChildGoogleshoppingxmlFeedCountryQuery innerJoinCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the Country relation
 *
 * @method     ChildGoogleshoppingxmlFeedCountry findOne(ConnectionInterface $con = null) Return the first ChildGoogleshoppingxmlFeedCountry matching the query
 * @method     ChildGoogleshoppingxmlFeedCountry findOneOrCreate(ConnectionInterface $con = null) Return the first ChildGoogleshoppingxmlFeedCountry matching the query, or a new ChildGoogleshoppingxmlFeedCountry object populated from the query conditions when no match is found
 *
 * @method     ChildGoogleshoppingxmlFeedCountry findOneById(int $id) Return the first ChildGoogleshoppingxmlFeedCountry filtered by the id column
 * @method     ChildGoogleshoppingxmlFeedCountry findOneByFeedId(int $feed_id) Return the first ChildGoogleshoppingxmlFeedCountry filtered by the feed_id column
 * @method     ChildGoogleshoppingxmlFeedCountry findOneByCountryId(int $country_id) Return the first ChildGoogleshoppingxmlFeedCountry filtered by the country_id column
 *
 * @method     array findById(int $id) Return ChildGoogleshoppingxmlFeedCountry objects filtered by the id column
 * @method     array findByFeedId(int $feed_id) Return ChildGoogleshoppingxmlFeedCountry objects filtered by the feed_id column
 * @method     array findByCountryId(int $country_id) Return ChildGoogleshoppingxmlFeedCountry objects filtered by the country_id column
 *
 */
abstract class GoogleshoppingxmlFeedCountryQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \GoogleShoppingXml\Model\Base\GoogleshoppingxmlFeedCountryQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\GoogleShoppingXml\\Model\\GoogleshoppingxmlFeedCountry', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildGoogleshoppingxmlFeedCountryQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountryQuery) {
            return $criteria;
        }
        $query = new \GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountryQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildGoogleshoppingxmlFeedCountry|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = GoogleshoppingxmlFeedCountryTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(GoogleshoppingxmlFeedCountryTableMap::DATABASE_NAME);
        }
        $this->basePreSelect($con);
        if ($this->formatter || $this->modelAlias || $this->with || $this->select
         || $this->selectColumns || $this->asColumns || $this->selectModifiers
         || $this->map || $this->having || $this->joins) {
            return $this->findPkComplex($key, $con);
        } else {
            return $this->findPkSimple($key, $con);
        }
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return   ChildGoogleshoppingxmlFeedCountry A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, FEED_ID, COUNTRY_ID FROM googleshoppingxml_feed_country WHERE ID = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new ChildGoogleshoppingxmlFeedCountry();
            $obj->hydrate($row);
            GoogleshoppingxmlFeedCountryTableMap::addInstanceToPool($obj, (string) $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return ChildGoogleshoppingxmlFeedCountry|array|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($dataFetcher);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(12, 56, 832), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ObjectCollection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getReadConnection($this->getDbName());
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($dataFetcher);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE id = 1234
     * $query->filterById(array(12, 34)); // WHERE id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the feed_id column
     *
     * Example usage:
     * <code>
     * $query->filterByFeedId(1234); // WHERE feed_id = 1234
     * $query->filterByFeedId(array(12, 34)); // WHERE feed_id IN (12, 34)
     * $query->filterByFeedId(array('min' => 12)); // WHERE feed_id > 12
     * </code>
     *
     * @see       filterByGoogleshoppingxmlFeed()
     *
     * @param     mixed $feedId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery The current query, for fluid interface
     */
    public function filterByFeedId($feedId = null, $comparison = null)
    {
        if (is_array($feedId)) {
            $useMinMax = false;
            if (isset($feedId['min'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::FEED_ID, $feedId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($feedId['max'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::FEED_ID, $feedId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::FEED_ID, $feedId, $comparison);
    }

    /**
     * Filter the query on the country_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCountryId(1234); // WHERE country_id = 1234
     * $query->filterByCountryId(array(12, 34)); // WHERE country_id IN (12, 34)
     * $query->filterByCountryId(array('min' => 12)); // WHERE country_id > 12
     * </code>
     *
     * @see       filterByCountry()
     *
     * @param     mixed $countryId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery The current query, for fluid interface
     */
    public function filterByCountryId($countryId = null, $comparison = null)
    {
        if (is_array($countryId)) {
            $useMinMax = false;
            if (isset($countryId['min'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::COUNTRY_ID, $countryId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($countryId['max'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::COUNTRY_ID, $countryId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::COUNTRY_ID, $countryId, $comparison);
    }

    /**
     * Filter the query by a related \GoogleShoppingXml\Model\GoogleshoppingxmlFeed object
     *
     * @param \GoogleShoppingXml\Model\GoogleshoppingxmlFeed|ObjectCollection $googleshoppingxmlFeed The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery The current query, for fluid interface
     */
    public function filterByGoogleshoppingxmlFeed($googleshoppingxmlFeed, $comparison = null)
    {
        if ($googleshoppingxmlFeed instanceof \GoogleShoppingXml\Model\GoogleshoppingxmlFeed) {
            return $this
                ->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::FEED_ID, $googleshoppingxmlFeed->getId(), $comparison);
        } elseif ($googleshoppingxmlFeed instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::FEED_ID, $googleshoppingxmlFeed->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByGoogleshoppingxmlFeed() only accepts arguments of type \GoogleShoppingXml\Model\GoogleshoppingxmlFeed or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the GoogleshoppingxmlFeed relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery The current query, for fluid interface
     */
    public function joinGoogleshoppingxmlFeed($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('GoogleshoppingxmlFeed');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'GoogleshoppingxmlFeed');
        }

        return $this;
    }

    /**
     * Use the GoogleshoppingxmlFeed relation GoogleshoppingxmlFeed object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery A secondary query class using the current class as primary query
     */
    public function useGoogleshoppingxmlFeedQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinGoogleshoppingxmlFeed($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'GoogleshoppingxmlFeed', '\GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Country object
     *
     * @param \Thelia\Model\Country|ObjectCollection $country The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery The current query, for fluid interface
     */
    public function filterByCountry($country, $comparison = null)
    {
        if ($country instanceof \Thelia\Model\Country) {
            return $this
                ->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::COUNTRY_ID, $country->getId(), $comparison);
        } elseif ($country instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::COUNTRY_ID, $country->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCountry() only accepts arguments of type \Thelia\Model\Country or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Country relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery The current query, for fluid interface
     */
    public function joinCountry($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Country');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'Country');
        }

        return $this;
    }

    /**
     * Use the Country relation Country object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CountryQuery A secondary query class using the current class as primary query
     */
    public function useCountryQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinCountry($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Country', '\Thelia\Model\CountryQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildGoogleshoppingxmlFeedCountry $googleshoppingxmlFeedCountry Object to remove from the list of results
     *
     * @return ChildGoogleshoppingxmlFeedCountryQuery The current query, for fluid interface
     */
    public function prune($googleshoppingxmlFeedCountry = null)
    {
        if ($googleshoppingxmlFeedCountry) {
            $this->addUsingAlias(GoogleshoppingxmlFeedCountryTableMap::ID, $googleshoppingxmlFeedCountry->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the googleshoppingxml_feed_country table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(GoogleshoppingxmlFeedCountryTableMap::DATABASE_NAME);
        }
        $affectedRows = 0; // initialize var to track total num of affected rows
        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            GoogleshoppingxmlFeedCountryTableMap::clearInstancePool();
            GoogleshoppingxmlFeedCountryTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildGoogleshoppingxmlFeedCountry or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildGoogleshoppingxmlFeedCountry object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
     public function delete(ConnectionInterface $con = null)
     {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(GoogleshoppingxmlFeedCountryTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(GoogleshoppingxmlFeedCountryTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        GoogleshoppingxmlFeedCountryTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            GoogleshoppingxmlFeedCountryTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // GoogleshoppingxmlFeedCountryQuery
