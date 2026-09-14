<?php

namespace GoogleShoppingXml\Form;

use GoogleShoppingXml\GoogleShoppingXml;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\CountryQuery;

class FeedManagementForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add('id', NumberType::class, array(
                'required' => false
            ))
            ->add('feed_label', TextType::class, array(
                'required' => true,
                'label' => Translator::getInstance()->trans('Feed label', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'title'
                ),
            ))
            ->add('lang_id', TextType::class, array(
                'required' => true,
                'label' => Translator::getInstance()->trans('Lang', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'lang_id'
                )
            ))
            ->add('country_id', TextType::class, array(
                'required' => true,
                'label' => Translator::getInstance()->trans('Country', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'country_id'
                )
            ))
            ->add("currency_id", TextType::class, array(
                'required' => true,
                'label' => Translator::getInstance()->trans('Currency', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'currency_id'
                )
            ))
            ->add('shipping_country_ids', ChoiceType::class, array(
                'required' => false,
                'multiple' => true,
                'choices' => $this->getCountryChoices(),
                'label' => Translator::getInstance()->trans('Shipping countries', array(), GoogleShoppingXml::DOMAIN_NAME),
                'label_attr' => array(
                    'for' => 'shipping_country_ids'
                )
            ));
    }

    /**
     * Countries the feed may advertise delivery to. Listing them as choices keeps a submitted
     * id from reaching the database unchecked; leaving the selection empty falls back to every
     * country a live carrier serves.
     *
     * @return array<string, int>
     */
    private function getCountryChoices()
    {
        $locale = Translator::getInstance()->getLocale();
        $choices = array();

        foreach (CountryQuery::create()->filterByVisible(1)->find() as $country) {
            $country->setLocale($locale);
            $choices[$country->getTitle()] = $country->getId();
        }

        return $choices;
    }
}
