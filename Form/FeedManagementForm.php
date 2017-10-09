<?php


namespace GoogleShoppingXml\Form;

use GoogleShoppingXml\GoogleShoppingXml;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\CountryQuery;
use Symfony\Component\Validator\Constraints;

class FeedManagementForm extends BaseForm
{

    protected function buildForm()
    {
        $this->formBuilder
            ->add('id', 'number', array(
                'required'    => false
            ))
            ->add('feed_label', 'text', array(
                'required'    => true,
                'label' => Translator::getInstance()->trans('Feed label', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'title'
                ),
            ))
            ->add('lang_id', 'text', array(
                'required'    => true,
                'label' => Translator::getInstance()->trans('Lang', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'lang_id'
                )
            ))
            ->add('country_list_id', 'choice', array(
                'required'    => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                ),
                'multiple'    => true,
                'choices'     => $this->getCountriesIdArray(),
                'label' => Translator::getInstance()->trans('Countries', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'country_id'
                )
            ))
            ->add("currency_id", "text", array(
                'required'    => true,
                'label' => Translator::getInstance()->trans('Currency', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'currency_id'
                )
            ));
    }

    private function getCountriesIdArray()
    {
        $countries = CountryQuery::create()
            ->select("id")
            ->find()
            ->toArray()
        ;

        $ids = [];

        foreach ($countries as $country) {
            $ids[$country] = $country;
        }

        return $ids;
    }

    public function getName()
    {
        return "googleshoppingxml_feed_configuration";
    }
}
