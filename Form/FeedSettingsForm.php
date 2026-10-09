<?php

declare(strict_types=1);

namespace GoogleShoppingXml\Form;

use GoogleShoppingXml\GoogleShoppingXml;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Regex;
use Thelia\Form\BaseForm;

/**
 * The content of every feed: stock rule, features and attributes read for color, gender, material and
 * size, subtitle as a product detail, image filter set. Ids are written as a comma separated list.
 */
class FeedSettingsForm extends BaseForm
{
    private const ID_LIST = '/^\s*(\d+\s*(,\s*\d+\s*)*)?$/';

    protected function buildForm(): void
    {
        $idList = new Regex(pattern: self::ID_LIST, message: 'A comma separated list of ids is expected.');

        $this->formBuilder
            ->add('exclude_out_of_stock', CheckboxType::class, ['required' => false, 'label' => 'Leave out the combinations without stock'])
            ->add('color_feature_ids', TextType::class, ['required' => false, 'label' => 'Features read for the color (ids)', 'constraints' => [$idList]])
            ->add('gender_feature_ids', TextType::class, ['required' => false, 'label' => 'Features read for the gender (ids)', 'constraints' => [$idList]])
            ->add('material_feature_ids', TextType::class, ['required' => false, 'label' => 'Features read for the material (ids)', 'constraints' => [$idList]])
            ->add('size_attribute_ids', TextType::class, ['required' => false, 'label' => 'Attributes read for the size (ids, no size when empty)', 'constraints' => [$idList]])
            ->add('subtitle_detail', CheckboxType::class, ['required' => false, 'label' => 'Send the product subtitle as a product detail'])
            ->add('image_filter', TextType::class, ['required' => false, 'label' => 'Image filter set', 'empty_data' => GoogleShoppingXml::DEFAULT_IMAGE_FILTER]);
    }

    public static function getName(): string
    {
        return 'googleshoppingxml_feed_settings';
    }
}
