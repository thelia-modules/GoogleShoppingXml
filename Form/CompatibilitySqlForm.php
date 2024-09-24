<?php

namespace GoogleShoppingXml\Form;

use GoogleShoppingXml\GoogleShoppingXml;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class CompatibilitySqlForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'enable_optimisation',
                CheckboxType::class,
                [
                    'label' => Translator::getInstance()->trans("Enable sql 8 optimisations"),
                    'label_attr' => array(
                        'for' => 'enable_sql_8'
                    ),
                    'data' => (bool)GoogleShoppingXml::getConfigValue(GoogleShoppingXml::ENABLE_SQL_8_COMPATIBILITY),
                    'required'=> false
                ]
            );
    }

}