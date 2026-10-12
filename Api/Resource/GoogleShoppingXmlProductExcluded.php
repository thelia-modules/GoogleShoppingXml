<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Api\Resource;

use GoogleShoppingXml\Model\GoogleshoppingxmlProductExcluded as GoogleshoppingxmlProductExcludedModel;
use GoogleShoppingXml\Model\GoogleshoppingxmlProductExcludedQuery;
use GoogleShoppingXml\Model\Map\GoogleshoppingxmlProductExcludedTableMap;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
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
    use ResourceAddonTrait;

    public ProductSaleElementsResource $productSaleElements;

    #[Groups([ProductSaleElementsResource::GROUP_ADMIN_READ, ProductSaleElementsResource::GROUP_ADMIN_WRITE])]
    public bool $isExcluded = false;

    /**
     * @throws PropelException
     */
    public function buildFromModel(ActiveRecordInterface|ProductSaleElements $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
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
        $model = GoogleshoppingxmlProductExcludedQuery::create()->filterByPseId($activeRecord->getId())->findOne();
        if (null === $model) {
            $model = new GoogleshoppingxmlProductExcludedModel();
            $model->setProductSaleElements($activeRecord);
        }

        $model->setIsExcluded($this->isExcluded() ? 1 : 0);
        $model->save();
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
