<?php

declare(strict_types=1);

/*
 * Stands in for the Thelia, Propel, API Platform and Symfony classes the API extension relies on, so that its unit
 * tests run without the Thelia kernel. Each declaration is skipped when the real class is already loaded.
 */

namespace ApiPlatform\Metadata {
    if (!class_exists(Operation::class, false)) {
        class Operation
        {
        }
    }
}

namespace Propel\Runtime\ActiveRecord {
    if (!interface_exists(ActiveRecordInterface::class, false)) {
        interface ActiveRecordInterface
        {
        }
    }
}

namespace Propel\Runtime\ActiveQuery {
    if (!class_exists(ModelCriteria::class, false)) {
        class ModelCriteria
        {
            public bool $joined = false;
        }
    }
}

namespace Propel\Runtime\Exception {
    if (!class_exists(PropelException::class, false)) {
        class PropelException extends \Exception
        {
        }
    }
}

namespace Propel\Runtime\Map {
    if (!class_exists(TableMap::class, false)) {
        class TableMap
        {
        }
    }
}

namespace Symfony\Component\HttpKernel\Exception {
    if (!class_exists(UnprocessableEntityHttpException::class, false)) {
        class UnprocessableEntityHttpException extends \RuntimeException
        {
        }
    }
}

namespace Thelia\Model {
    if (!class_exists(ProductSaleElements::class, false)) {
        class ProductSaleElements implements \Propel\Runtime\ActiveRecord\ActiveRecordInterface
        {
            public function __construct(private readonly int $id = 0)
            {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function hasVirtualColumn(string $name): bool
            {
                return false;
            }
        }
    }
}

namespace Thelia\Api\Resource {
    if (!interface_exists(PropelResourceInterface::class, false)) {
        interface PropelResourceInterface
        {
        }
    }

    if (!interface_exists(ResourceAddonInterface::class, false)) {
        interface ResourceAddonInterface
        {
        }
    }

    if (!class_exists(ProductSaleElements::class, false)) {
        class ProductSaleElements implements PropelResourceInterface
        {
            public const GROUP_ADMIN_READ = 'product_sale_elements:admin:read';
            public const GROUP_ADMIN_WRITE = 'product_sale_elements:admin:write';
        }
    }

    if (!trait_exists(ResourceAddonTrait::class, false)) {
        /**
         * What the real trait does when the table exists: the join, and the delete by "id".
         */
        trait ResourceAddonTrait
        {
            public static bool $joinAdded = false;

            public static function extendQuery(\Propel\Runtime\ActiveQuery\ModelCriteria $query, ?\ApiPlatform\Metadata\Operation $operation = null, array $context = []): void
            {
                $query->joined = true;
                self::$joinAdded = true;
            }
        }
    }
}

namespace GoogleShoppingXml\Model {
    if (!class_exists(GoogleshoppingxmlProductExcludedQuery::class, false)) {
        /**
         * The generated query of a table whose key is "pse_id": it has no filterById().
         */
        class GoogleshoppingxmlProductExcludedQuery
        {
            /** @var list<string> */
            public static array $calls = [];

            public static function create(): self
            {
                return new self();
            }

            public function filterByPseId(int $pseId): self
            {
                self::$calls[] = 'filterByPseId('.$pseId.')';

                return $this;
            }

            public function delete(): int
            {
                self::$calls[] = 'delete';

                return 1;
            }
        }
    }
}
