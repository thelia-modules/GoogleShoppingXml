<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Api\Resource;

use GoogleShoppingXml\Api\ExclusionTable;
use GoogleShoppingXml\Model\GoogleshoppingxmlProductExcluded as GoogleshoppingxmlProductExcludedModel;
use GoogleShoppingXml\Model\GoogleshoppingxmlProductExcludedQuery;
use GoogleShoppingXml\Model\Map\GoogleshoppingxmlProductExcludedTableMap;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Attribute\Groups;
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\ResourceAddonTrait;
use Thelia\Model\ProductSaleElements;

/**
 * The box "excluded from Google Shopping" of a combination, read and written through the admin API of the
 * combinations (as the exclusion of FacebookFeed).
 */
class GoogleShoppingXmlProductExcluded implements ResourceAddonInterface
{
    use ResourceAddonTrait {
        extendQuery as private extendQueryWithJoin;
    }

    public ProductSaleElementsResource $productSaleElements;

    #[Groups([ProductSaleElementsResource::GROUP_ADMIN_READ, ProductSaleElementsResource::GROUP_ADMIN_WRITE])]
    public bool $isExcluded = false;

    /**
     * Without the table (module flagged active but its tables not created yet) the combinations are read without
     * the join, and none of them is reported as excluded.
     */
    public static function extendQuery(ModelCriteria $query, ?Operation $operation = null, array $context = []): void
    {
        if (!ExclusionTable::exists()) {
            return;
        }

        self::extendQueryWithJoin($query, $operation, $context);
    }

    /**
     * @throws PropelException
     */
    public function buildFromModel(ActiveRecordInterface|ProductSaleElements $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        if (!ExclusionTable::exists()) {
            return $this;
        }

        if ($activeRecord->hasVirtualColumn('GoogleShoppingXmlProductExcluded_is_excluded')) {
            $this->setIsExcluded((bool) $activeRecord->getVirtualColumn('GoogleShoppingXmlProductExcluded_is_excluded'));

            return $this;
        }

        if (null !== $productExcluded = GoogleshoppingxmlProductExcludedQuery::create()->filterByProductSaleElements($activeRecord)->findOne()) {
            $this->setIsExcluded((bool) $productExcluded->getIsExcluded());
        }

        return $this;
    }

    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        if (isset($data['isExcluded'])) {
            $this->setIsExcluded((bool) $data['isExcluded']);
        }

        return $this;
    }

    /**
     * @throws PropelException
     */
    public function doSave(ActiveRecordInterface|ProductSaleElements $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        if (!ExclusionTable::exists()) {
            if (!$this->isExcluded()) {
                return;
            }

            throw new UnprocessableEntityHttpException(sprintf('The combination cannot be excluded from Google Shopping: the table "%s" is missing. Run "php bin/console module:schema:apply GoogleShoppingXml".', ExclusionTable::NAME));
        }

        $model = GoogleshoppingxmlProductExcludedQuery::create()->filterByPseId($activeRecord->getId())->findOne();
        if (null === $model) {
            $model = new GoogleshoppingxmlProductExcludedModel();
            $model->setProductSaleElements($activeRecord);
        }

        $model->setIsExcluded($this->isExcluded() ? 1 : 0);
        $model->save();
    }

    public function doDelete(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        if (!ExclusionTable::exists()) {
            return;
        }

        GoogleshoppingxmlProductExcludedQuery::create()->filterByPseId($activeRecord->getId())->delete();
    }

    public function isExcluded(): bool
    {
        return $this->isExcluded;
    }

    public function setIsExcluded(bool $isExcluded): GoogleShoppingXmlProductExcluded
    {
        $this->isExcluded = $isExcluded;

        return $this;
    }

    public function getProductSaleElements(): ProductSaleElementsResource
    {
        return $this->productSaleElements;
    }

    public function setProductSaleElements(ProductSaleElementsResource $productSaleElements): GoogleShoppingXmlProductExcluded
    {
        $this->productSaleElements = $productSaleElements;

        return $this;
    }

    public static function getResourceParent(): string
    {
        return ProductSaleElementsResource::class;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return new GoogleshoppingxmlProductExcludedTableMap();
    }
}
