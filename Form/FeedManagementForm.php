<?php


namespace GoogleShoppingXml\Form;

use GoogleShoppingXml\GoogleShoppingXml;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\CountryQuery;
use Symfony\Component\Validator\Constraints;

class FeedManagementForm extends BaseForm
{

    protected function buildForm()
    {
        $this->formBuilder
            ->add('id', NumberType::class, array(
                'required'    => false
            ))
            ->add('feed_label', TextType::class, array(
                'required'    => true,
                'label' => Translator::getInstance()->trans('Feed label', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'title'
                ),
            ))
            ->add('lang_id', TextType::class, array(
                'required'    => true,
                'label' => Translator::getInstance()->trans('Lang', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'lang_id'
                )
            ))
            ->add('country_id', TextType::class, array(
                'required'    => true,
                'label' => Translator::getInstance()->trans('Country', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'country_id'
                )
            ))
            ->add("currency_id", TextType::class, array(
                'required'    => true,
                'label' => Translator::getInstance()->trans('Currency', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'currency_id'
                )
            ));
    }

    public static function getName()
    {
        return "googleshoppingxml_feed_configuration";
    }
}
