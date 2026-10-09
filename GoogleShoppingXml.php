<?php

namespace GoogleShoppingXml;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Install\Database;
use Thelia\Module\BaseModule;

class GoogleShoppingXml extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'googleshoppingxml';
    const DOMAIN_BO_DEFAULT = "googleshoppingxml.bo.default";

    /* @var string */
    const UPDATE_PATH = __DIR__ . DS . 'Config' . DS . 'update';

    const ENABLE_SQL_8_COMPATIBILITY = 'enable_sql_8_compatibility';

    /** Settings of the feed content (Feed\FeedSettings). */
    public const EAN_RULE = 'ean_rule';
    public const EXCLUDE_OUT_OF_STOCK = 'googleshoppingxml.exclude_out_of_stock';
    public const FEATURE_COLOR_IDS = 'googleshoppingxml.feature_color_ids';
    public const FEATURE_GENDER_IDS = 'googleshoppingxml.feature_gender_ids';
    public const FEATURE_MATERIAL_IDS = 'googleshoppingxml.feature_material_ids';
    public const ATTRIBUTE_SIZE_IDS = 'googleshoppingxml.attribute_size_ids';
    public const SUBTITLE_DETAIL = 'googleshoppingxml.subtitle_detail';
    public const IMAGE_FILTER = 'googleshoppingxml.image_filter';
    public const DEFAULT_IMAGE_FILTER = 'default';

    public function preActivation(?ConnectionInterface $con = null): bool
    {
        if (!$this->getConfigValue('is_initialized', false)) {
            $database = new Database($con);

            $database->insertSql(null, array(__DIR__ . '/Config/thelia.sql'));

            $this->setConfigValue('is_initialized', true);
        }
        
        return true;
    }

    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        $finder = (new Finder())->files()->name('#.*?\.sql#')->sortByName()->in(self::UPDATE_PATH);

        if ($finder->count() === 0) {
            return;
        }

        $database = new Database($con);

        /** @var \Symfony\Component\Finder\SplFileInfo $updateSQLFile */
        foreach ($finder as $updateSQLFile) {
            if (version_compare($currentVersion, str_replace('.sql', '', $updateSQLFile->getFilename()), '<')) {
                $database->insertSql(null, [$updateSQLFile->getPathname()]);
            }
        }
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/I18n/*', __DIR__.'/Config/**/*.php', __DIR__.'/GoogleShoppingXml.php', __DIR__.'/Tests/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
