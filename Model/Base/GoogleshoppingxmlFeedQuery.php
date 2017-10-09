<?php

namespace GoogleShoppingXml\Model\Base;

use \Exception;
use \PDO;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeed as ChildGoogleshoppingxmlFeed;
use GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery as ChildGoogleshoppingxmlFeedQuery;
use GoogleShoppingXml\Model\Map\GoogleshoppingxmlFeedTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Currency;
use Thelia\Model\Lang;

/**
 * Base class that represents a query for the 'googleshoppingxml_feed' table.
 *
 *
 *
 * @method     ChildGoogleshoppingxmlFeedQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildGoogleshoppingxmlFeedQuery orderByLabel($order = Criteria::ASC) Order by the label column
 * @method     ChildGoogleshoppingxmlFeedQuery orderByLangId($order = Criteria::ASC) Order by the lang_id column
 * @method     ChildGoogleshoppingxmlFeedQuery orderByCurrencyId($order = Criteria::ASC) Order by the currency_id column
 *
 * @method     ChildGoogleshoppingxmlFeedQuery groupById() Group by the id column
 * @method     ChildGoogleshoppingxmlFeedQuery groupByLabel() Group by the label column
 * @method     ChildGoogleshoppingxmlFeedQuery groupByLangId() Group by the lang_id column
 * @method     ChildGoogleshoppingxmlFeedQuery groupByCurrencyId() Group by the currency_id column
 *
 * @method     ChildGoogleshoppingxmlFeedQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildGoogleshoppingxmlFeedQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildGoogleshoppingxmlFeedQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildGoogleshoppingxmlFeedQuery leftJoinLang($relationAlias = null) Adds a LEFT JOIN clause to the query using the Lang relation
 * @method     ChildGoogleshoppingxmlFeedQuery rightJoinLang($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Lang relation
 * @method     ChildGoogleshoppingxmlFeedQuery innerJoinLang($relationAlias = null) Adds a INNER JOIN clause to the query using the Lang relation
 *
 * @method     ChildGoogleshoppingxmlFeedQuery leftJoinCurrency($relationAlias = null) Adds a LEFT JOIN clause to the query using the Currency relation
 * @method     ChildGoogleshoppingxmlFeedQuery rightJoinCurrency($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Currency relation
 * @method     ChildGoogleshoppingxmlFeedQuery innerJoinCurrency($relationAlias = null) Adds a INNER JOIN clause to the query using the Currency relation
 *
 * @method     ChildGoogleshoppingxmlFeedQuery leftJoinGoogleshoppingxmlFeedCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the GoogleshoppingxmlFeedCountry relation
 * @method     ChildGoogleshoppingxmlFeedQuery rightJoinGoogleshoppingxmlFeedCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the GoogleshoppingxmlFeedCountry relation
 * @method     ChildGoogleshoppingxmlFeedQuery innerJoinGoogleshoppingxmlFeedCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the GoogleshoppingxmlFeedCountry relation
 *
 * @method     ChildGoogleshoppingxmlFeed findOne(ConnectionInterface $con = null) Return the first ChildGoogleshoppingxmlFeed matching the query
 * @method     ChildGoogleshoppingxmlFeed findOneOrCreate(ConnectionInterface $con = null) Return the first ChildGoogleshoppingxmlFeed matching the query, or a new ChildGoogleshoppingxmlFeed object populated from the query conditions when no match is found
 *
 * @method     ChildGoogleshoppingxmlFeed findOneById(int $id) Return the first ChildGoogleshoppingxmlFeed filtered by the id column
 * @method     ChildGoogleshoppingxmlFeed findOneByLabel(string $label) Return the first ChildGoogleshoppingxmlFeed filtered by the label column
 * @method     ChildGoogleshoppingxmlFeed findOneByLangId(int $lang_id) Return the first ChildGoogleshoppingxmlFeed filtered by the lang_id column
 * @method     ChildGoogleshoppingxmlFeed findOneByCurrencyId(int $currency_id) Return the first ChildGoogleshoppingxmlFeed filtered by the currency_id column
 *
 * @method     array findById(int $id) Return ChildGoogleshoppingxmlFeed objects filtered by the id column
 * @method     array findByLabel(string $label) Return ChildGoogleshoppingxmlFeed objects filtered by the label column
 * @method     array findByLangId(int $lang_id) Return ChildGoogleshoppingxmlFeed objects filtered by the lang_id column
 * @method     array findByCurrencyId(int $currency_id) Return ChildGoogleshoppingxmlFeed objects filtered by the currency_id column
 *
 */
abstract class GoogleshoppingxmlFeedQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \GoogleShoppingXml\Model\Base\GoogleshoppingxmlFeedQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\GoogleShoppingXml\\Model\\GoogleshoppingxmlFeed', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildGoogleshoppingxmlFeedQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildGoogleshoppingxmlFeedQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery) {
            return $criteria;
        }
        $query = new \GoogleShoppingXml\Model\GoogleshoppingxmlFeedQuery();
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
     * @return ChildGoogleshoppingxmlFeed|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = GoogleshoppingxmlFeedTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(GoogleshoppingxmlFeedTableMap::DATABASE_NAME);
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
     * @return   ChildGoogleshoppingxmlFeed A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, LABEL, LANG_ID, CURRENCY_ID FROM googleshoppingxml_feed WHERE ID = :p0';
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
            $obj = new ChildGoogleshoppingxmlFeed();
            $obj->hydrate($row);
            GoogleshoppingxmlFeedTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildGoogleshoppingxmlFeed|array|mixed the result, formatted by the current formatter
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
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the label column
     *
     * Example usage:
     * <code>
     * $query->filterByLabel('fooValue');   // WHERE label = 'fooValue'
     * $query->filterByLabel('%fooValue%'); // WHERE label LIKE '%fooValue%'
     * </code>
     *
     * @param     string $label The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function filterByLabel($label = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($label)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $label)) {
                $label = str_replace('*', '%', $label);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::LABEL, $label, $comparison);
    }

    /**
     * Filter the query on the lang_id column
     *
     * Example usage:
     * <code>
     * $query->filterByLangId(1234); // WHERE lang_id = 1234
     * $query->filterByLangId(array(12, 34)); // WHERE lang_id IN (12, 34)
     * $query->filterByLangId(array('min' => 12)); // WHERE lang_id > 12
     * </code>
     *
     * @see       filterByLang()
     *
     * @param     mixed $langId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function filterByLangId($langId = null, $comparison = null)
    {
        if (is_array($langId)) {
            $useMinMax = false;
            if (isset($langId['min'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::LANG_ID, $langId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($langId['max'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::LANG_ID, $langId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::LANG_ID, $langId, $comparison);
    }

    /**
     * Filter the query on the currency_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCurrencyId(1234); // WHERE currency_id = 1234
     * $query->filterByCurrencyId(array(12, 34)); // WHERE currency_id IN (12, 34)
     * $query->filterByCurrencyId(array('min' => 12)); // WHERE currency_id > 12
     * </code>
     *
     * @see       filterByCurrency()
     *
     * @param     mixed $currencyId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function filterByCurrencyId($currencyId = null, $comparison = null)
    {
        if (is_array($currencyId)) {
            $useMinMax = false;
            if (isset($currencyId['min'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::CURRENCY_ID, $currencyId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($currencyId['max'])) {
                $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::CURRENCY_ID, $currencyId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::CURRENCY_ID, $currencyId, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Lang object
     *
     * @param \Thelia\Model\Lang|ObjectCollection $lang The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function filterByLang($lang, $comparison = null)
    {
        if ($lang instanceof \Thelia\Model\Lang) {
            return $this
                ->addUsingAlias(GoogleshoppingxmlFeedTableMap::LANG_ID, $lang->getId(), $comparison);
        } elseif ($lang instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(GoogleshoppingxmlFeedTableMap::LANG_ID, $lang->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByLang() only accepts arguments of type \Thelia\Model\Lang or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Lang relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function joinLang($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Lang');

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
            $this->addJoinObject($join, 'Lang');
        }

        return $this;
    }

    /**
     * Use the Lang relation Lang object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\LangQuery A secondary query class using the current class as primary query
     */
    public function useLangQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinLang($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Lang', '\Thelia\Model\LangQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Currency object
     *
     * @param \Thelia\Model\Currency|ObjectCollection $currency The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function filterByCurrency($currency, $comparison = null)
    {
        if ($currency instanceof \Thelia\Model\Currency) {
            return $this
                ->addUsingAlias(GoogleshoppingxmlFeedTableMap::CURRENCY_ID, $currency->getId(), $comparison);
        } elseif ($currency instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(GoogleshoppingxmlFeedTableMap::CURRENCY_ID, $currency->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCurrency() only accepts arguments of type \Thelia\Model\Currency or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Currency relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function joinCurrency($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Currency');

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
            $this->addJoinObject($join, 'Currency');
        }

        return $this;
    }

    /**
     * Use the Currency relation Currency object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CurrencyQuery A secondary query class using the current class as primary query
     */
    public function useCurrencyQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinCurrency($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Currency', '\Thelia\Model\CurrencyQuery');
    }

    /**
     * Filter the query by a related \GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountry object
     *
     * @param \GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountry|ObjectCollection $googleshoppingxmlFeedCountry  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function filterByGoogleshoppingxmlFeedCountry($googleshoppingxmlFeedCountry, $comparison = null)
    {
        if ($googleshoppingxmlFeedCountry instanceof \GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountry) {
            return $this
                ->addUsingAlias(GoogleshoppingxmlFeedTableMap::ID, $googleshoppingxmlFeedCountry->getFeedId(), $comparison);
        } elseif ($googleshoppingxmlFeedCountry instanceof ObjectCollection) {
            return $this
                ->useGoogleshoppingxmlFeedCountryQuery()
                ->filterByPrimaryKeys($googleshoppingxmlFeedCountry->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByGoogleshoppingxmlFeedCountry() only accepts arguments of type \GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountry or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the GoogleshoppingxmlFeedCountry relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function joinGoogleshoppingxmlFeedCountry($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('GoogleshoppingxmlFeedCountry');

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
            $this->addJoinObject($join, 'GoogleshoppingxmlFeedCountry');
        }

        return $this;
    }

    /**
     * Use the GoogleshoppingxmlFeedCountry relation GoogleshoppingxmlFeedCountry object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountryQuery A secondary query class using the current class as primary query
     */
    public function useGoogleshoppingxmlFeedCountryQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinGoogleshoppingxmlFeedCountry($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'GoogleshoppingxmlFeedCountry', '\GoogleShoppingXml\Model\GoogleshoppingxmlFeedCountryQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildGoogleshoppingxmlFeed $googleshoppingxmlFeed Object to remove from the list of results
     *
     * @return ChildGoogleshoppingxmlFeedQuery The current query, for fluid interface
     */
    public function prune($googleshoppingxmlFeed = null)
    {
        if ($googleshoppingxmlFeed) {
            $this->addUsingAlias(GoogleshoppingxmlFeedTableMap::ID, $googleshoppingxmlFeed->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the googleshoppingxml_feed table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(GoogleshoppingxmlFeedTableMap::DATABASE_NAME);
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
            GoogleshoppingxmlFeedTableMap::clearInstancePool();
            GoogleshoppingxmlFeedTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildGoogleshoppingxmlFeed or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildGoogleshoppingxmlFeed object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(GoogleshoppingxmlFeedTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(GoogleshoppingxmlFeedTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        GoogleshoppingxmlFeedTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            GoogleshoppingxmlFeedTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // GoogleshoppingxmlFeedQuery
