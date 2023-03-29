<?php


namespace GoogleShoppingXml\Form;

use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Form\BaseForm;
use Symfony\Component\Validator\Constraints;

class GoogleTaxonomyForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add("thelia_category_id", TextType::class, array(
                'required' => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                )
            ))
            ->add("google_category_id", NumberType::class, array(
                'required' => true,
                "constraints" => array(
                    new Constraints\NotBlank(),
                )
            ));
    }

    public function getName()
    {
        return "googleshoppingxml_taxonomy";
    }
}
